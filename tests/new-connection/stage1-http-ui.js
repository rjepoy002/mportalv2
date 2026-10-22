// Loaded only by the disposable fixture page, ahead of the shared prototype editor.
window.ncFixtureIntegration=(()=>{
 'use strict';let ui,receipt=null,review=null,dirty=false,pending=null,busy=false,generation=0,sessionExpired=false,mutation=null;
 const key=()=>[...crypto.getRandomValues(new Uint8Array(32))].map(b=>b.toString(16).padStart(2,'0')).join('');
 const expected=()=>Object.fromEntries(['application_version','revision_id','revision_no','revision_sha256'].map(k=>[k,receipt[k]]));
 const byId=id=>document.getElementById(id);
 const clearReview=()=>{review=null;for(const code of ['privacy_notice','information_accuracy','application_verification','physical_signing']){const e=ui.byPath('acknowledgments.'+code);e.checked=false;e.disabled=true;}ui.byPath('review.information_confirmed').checked=false;ui.byPath('review.information_confirmed').disabled=true;byId('check-readiness').disabled=true;renderReview();};
 function renderReview(){if(!ui)return;const target=byId('review-summary');target.replaceChildren();
  if(!review){target.textContent='Save changes, then request a fresh private review of the saved information.';return;}
  const snapshot=review.snapshot;
  for(const [section,title] of Object.entries({applicant:'Applicant Identity',residential_address:'Residential Address',service_location:'Proposed Service Location',service_request:'Service Request'})){
   const h=document.createElement('h3');h.textContent=title;const edit=document.createElement('button');edit.type='button';edit.textContent='Edit '+title;edit.onclick=()=>{clearReview();ui.show(Object.keys({applicant:0,residential_address:1,service_location:2,service_request:3}).indexOf(section));};const dl=document.createElement('dl');
   for(const [field,value] of Object.entries(snapshot[section])){if(['province_code','city_municipality_code','barangay_code','country_code','request_kind','profile_confirmed'].includes(field))continue;const dt=document.createElement('dt'),dd=document.createElement('dd');dt.textContent=field.replace(/_code$/,'').replaceAll('_',' ');const catalog={premises_classification_code:'premises',occupancy_code:'occupancies',connection_type_code:'connections'}[field],labels=review.catalog??ui.context.catalog;const shown=catalog?(labels[catalog]?.[value]?.name??labels[catalog]?.[value]??value):value;dd.textContent=shown===null?'Not provided':typeof shown==='boolean'?(shown?'Yes':'No'):String(shown);dl.append(dt,dd);}const group=document.createElement('details'),heading=document.createElement('summary');group.open=true;heading.append(h);group.append(heading,edit,dl);target.append(group);
  }
 }
 async function request(path,command=null,exact=null){
  const options={cache:'no-store',credentials:'same-origin'};
  if(command!==null||exact!==null)Object.assign(options,{method:'POST',headers:{'Content-Type':'application/json'},body:exact??JSON.stringify({csrf:ui.context.csrf,...command})});
  const response=await fetch(path,options),body=await response.json();
  if(!response.ok){const error=Error(body.message??'Test request failed.');error.status=response.status;error.errors=body.errors;throw error;}return body;
 }
 function failure(error){
  if([401,419].includes(error.status)){sessionExpired=true;mutation=null;byId("retry-command").hidden=true;pending=null;clearReview();byId('retry-submission').hidden=true;ui.fields.forEach(e=>e.disabled=true);for(const id of ['save-demo','request-review','check-readiness','create-application','next-section'])byId(id).disabled=true;byId('expiry-warning').textContent='Session expired. Reauthenticate to resume the saved draft.';const link=byId('fixture-reauth');link.hidden=false;link.href='/login'+(receipt?'?r='+receipt.reference:'');ui.status.textContent='Session expired. Reauthenticate and resume the last saved database draft; nothing will be submitted automatically.';}
  if(error.status===409){pending=null;clearReview();byId('retry-submission').hidden=true;ui.status.textContent='Conflict: resume the saved draft or request a fresh review. No stale changes were applied.';}
  if(error.status===403){pending=null;clearReview();byId('retry-submission').hidden=true;ui.status.textContent='Authorization or published test notice changed. Resume and request a fresh review.';}
  if(error.status===422){ui.showErrors(error.errors??{form:error.message});return;}
  byId('fixture-error-text').textContent=error.message;const dialog=byId('fixture-error');if(!dialog.open)dialog.showModal();
 }
 function acceptReceipt(result){pending=null;review=null;dirty=false;ui.setDirty(false);receipt=result;byId('retry-submission').hidden=true;byId('prototype-form').hidden=true;byId('create-application').hidden=true;byId('request-review').hidden=true;byId('section-nav').hidden=true;const panel=byId('submission-result');panel.hidden=false;panel.textContent='Stage 1 submitted in the disposable test database. Public reference: '+result.reference+'. This is a test confirmation, not PALECO application approval.';panel.focus();ui.status.textContent='Test submission recorded. No real application was submitted.';}
 async function resume(){if(busy||mutation||!receipt?.reference)return;busy=true;const resumeGeneration=generation;ui.status.textContent='Resuming disposable draft…';try{
  let result;try{result=await request('/api/draft?reference='+receipt.reference);}catch(error){if(error.status!==403)throw error;acceptReceipt(await request('/api/receipt?reference='+receipt.reference));return;}
  if(generation!==resumeGeneration){ui.status.textContent='New edits were preserved. Resume again to discard them explicitly.';return;}receipt=result;const prefill=result.application_version===0;
  if(prefill)for(const code of ['first_name','middle_name','last_name','extension_name','contact_number'])result.input.applicant[code]??=ui.context.input.applicant[code];
  ui.apply(result.input);dirty=prefill;ui.setDirty(prefill);pending=null;clearReview();byId('retry-submission').hidden=true;ui.status.textContent='Resumed saved disposable database draft.'+(prefill?' Structured profile prefill needs confirmation and saving.':'');
 }catch(error){failure(error);}finally{busy=false;}}

 async function mutate(kind){
  if(busy)return;if(mutation&&mutation.kind!==kind){failure(Error('Resolve the unknown command using Retry before another write.'));return;}
  busy=true;
  try{
   if(!mutation){if(kind==='save'&&!receipt)throw Error('Create a test application first.');clearReview();mutation={kind,generation,bytes:JSON.stringify({csrf:ui.context.csrf,...(kind==='create'?{key:key()}:{reference:receipt.reference,expected:expected(),key:key(),input:ui.read()})})};}
   const command=mutation,result=await request('/api/'+kind,null,command.bytes);receipt=result;mutation=null;byId('retry-command').hidden=true;
   if(kind==='create'){history.replaceState(null,'','/app?r='+receipt.reference);byId('create-application').hidden=true;ui.status.textContent='Created disposable application. Save the displayed test data.';busy=false;if(generation===command.generation)await resume();}
   else{dirty=generation!==command.generation;ui.setDirty(dirty);ui.status.textContent=dirty?'New edits remain unsaved. Save before a fresh private review.':'Saved to the disposable database. No actual portal draft was written.';}
  }catch(error){if((!error.status||error.status>=500)&&mutation){byId('retry-command').hidden=false;error=Error('Network result unknown. Retry the exact '+mutation.kind+' command; its original key and bytes are retained only in memory.');}else if(error.status){mutation=null;byId('retry-command').hidden=true;}failure(error);}finally{busy=false;}
 }
 async function save(){return mutate('save');}
 async function submit(){if(busy||mutation)return;busy=true;try{
  if(!review||dirty)throw Error('Save and request a fresh private review first.');
  pending=JSON.stringify({csrf:ui.context.csrf,reference:receipt.reference,expected:expected(),key:key(),proof:review.proof,selections:{review_confirmed:ui.byPath('review.information_confirmed').checked,acknowledgments:Object.fromEntries(['privacy_notice','information_accuracy','application_verification','physical_signing'].map(c=>[c,ui.byPath('acknowledgments.'+c).checked]))}});
  acceptReceipt(await request('/api/submit',null,pending));
 }catch(error){if((!error.status||error.status>=500)&&pending){byId('retry-submission').hidden=false;error=Error('Network result unknown. Use Retry exact test submission or Resume to retrieve the recorded outcome.');}else if(error.status!==401&&error.status!==419){pending=null;}failure(error);}finally{busy=false;}}
 function init(editor){ui=editor;byId('close-fixture-error').onclick=()=>byId('fixture-error').close();
  byId('refresh-catalog').hidden=!ui.context.fixtureGoverned;
  byId('refresh-catalog').onclick=async()=>{if(busy||mutation)return;busy=true;try{
   const result=await request('/api/catalog'),input=ui.read(),errors={};ui.context.catalog=result.catalog;
   for(const element of ui.fields){const field=element.dataset.path.split('.').at(-1),kind={province_code:'provinces',city_municipality_code:'cities',barangay_code:'barangays',premises_classification_code:'premises',occupancy_code:'occupancies',connection_type_code:'connections'}[field];if(!kind)continue;const value=element.value;const empty=document.createElement('option');empty.value='';empty.textContent='Select an approved test option';element.replaceChildren(empty);for(const [code,label] of Object.entries(result.catalog[kind])){const option=document.createElement('option');option.value=code;option.textContent=label.name??label;element.append(option);}const [section,name]=element.dataset.path.split('.');if(value&&!Object.hasOwn(result.catalog[kind],value)){input[section][name]=null;errors[element.dataset.path]='This test selection was removed. Choose a current option.';}}
   generation++;dirty=true;ui.apply(input);ui.setDirty(true);clearReview();ui.status.textContent='Published test catalog refreshed. Confirm selections and save before a fresh review.';if(Object.keys(errors).length)ui.showErrors(errors);
  }catch(error){failure(error);}finally{busy=false;}};
  byId('find-applications').onclick=async()=>{if(busy)return;busy=true;try{const result=await request('/api/applications');const list=byId('found-applications');list.replaceChildren();for(const app of result.applications){const button=document.createElement('button');button.type='button';button.textContent='Resume test '+app.reference+' ('+app.status+')';button.onclick=()=>{location.href='/app?r='+app.reference;};list.append(button);}if(!result.applications.length)list.textContent='No saved test applications found.';list.focus();}catch(error){failure(error);}finally{busy=false;}};
  byId('create-application').onclick=()=>mutate('create');
  byId('retry-command').onclick=()=>mutation&&mutate(mutation.kind);
  byId('resume-draft').onclick=()=>{if(!dirty||confirm('Discard unsaved test changes and resume the saved draft?'))resume();};
  byId('request-review').onclick=async()=>{if(busy||mutation)return;busy=true;const reviewedGeneration=generation;try{if(!receipt||dirty)throw Error('Save the draft before requesting private review.');clearReview();const candidate=await request('/api/review',{reference:receipt.reference,expected:expected()});if(generation!==reviewedGeneration)throw Error('New edits require saving and another review.');review=candidate;ui.show(4);for(const [code,notice] of Object.entries(review.notices)){const e=ui.byPath('acknowledgments.'+code);e.disabled=false;e.closest('label').querySelector('span').textContent='TEST '+notice.version+': '+notice.content;}ui.byPath('review.information_confirmed').disabled=false;byId('check-readiness').disabled=false;ui.status.textContent='Private review loaded from the saved database snapshot. Confirm every test acknowledgment.';}catch(error){failure(error);}finally{busy=false;}};
  byId('retry-submission').onclick=async()=>{if(busy||!pending)return;busy=true;try{acceptReceipt(await request('/api/submit',null,pending));}catch(error){failure(error);}finally{busy=false;}};
  queueMicrotask(()=>{clearReview();if(ui.context.fixtureReference){receipt={reference:ui.context.fixtureReference};byId('create-application').hidden=true;resume();}});
 }
 return {init,save,submit,renderReview,expired:()=>sessionExpired,changed(path){if(!path)return;if(path.startsWith('acknowledgments.')||path==='review.information_confirmed'){ui.status.textContent='Saved draft reviewed; test acknowledgments are not recorded until submission.';return;}generation++;dirty=true;ui.status.textContent='Unsaved changes. Save before a fresh private review.';pending=null;byId('retry-submission').hidden=true;clearReview();},async validate(action,input){try{return await request('/api/validate',{input,complete:action==='check'});}catch(error){failure(error);throw error;}}};
})();
