window.ncV2PersistentBridge=ui=>{
 const {cfg,read,apply,feedback,show,setDirty,getGeneration}=ui,get=id=>document.getElementById(id);let receipt=null,proof=null,busy=false,pending=null;
 const key=()=>[...crypto.getRandomValues(new Uint8Array(32))].map(b=>b.toString(16).padStart(2,'0')).join('');
 const expected=()=>receipt?Object.fromEntries(['application_version','revision_id','revision_no','revision_sha256'].map(k=>[k,receipt[k]])):null;
 const status=text=>get('v2-status').textContent=text;
 const clear=()=>{proof=null;get('confirmations.information_confirmed').checked=false;get('v2-review-output').replaceChildren();};
 const retry=document.createElement('button');retry.type='button';retry.textContent='Retry exact failed database request';retry.hidden=true;get('v2-draft-list').before(retry);
 async function request(bytes){const r=await fetch('/api',{method:'POST',headers:{'Content-Type':'application/json'},body:bytes,cache:'no-store'});let data;try{data=await r.json();}catch{throw Error('Response unavailable');}return {r,data};}
 function updateProfile(data){if(!data.profile)return;for(const [key,value] of Object.entries(data.profile))if(get('identity-'+key))get('identity-'+key).value=value??'';cfg.profile_fingerprint=data.profile_fingerprint;get('confirmations.profile_confirmed').checked=false;clear();}
 function remember(data){if(data.reference)receipt={...receipt,...data};}
 function render(snapshot){const target=get('v2-review-output');target.replaceChildren();
  const labels={first_name:'First name',middle_name:'Middle name',last_name:'Last name',extension_name:'Extension',contact_number:'Contact number',email:'Verified portal email',date_of_birth:'Date of birth',age_years:'Age',age_as_of:'Age as of',gender:'Gender',civil_status:'Civil status',education_code:'Educational attainment',education_other:'Education specification',occupation:'Occupation',income_source:'Source of income',annual_income_php:'Annual income (PHP)',dependent_count:'Number of dependents',religion:'Religion',social_affiliation:'Social affiliation',tin:'TIN',city_municipality_psgc:'Synthetic city / municipality',barangay_psgc:'Synthetic barangay',street_purok_sitio:'Street / Purok / Sitio',house_lot_building:'House / Lot / Building Number',postal_code:'Postal Code',landmark_directions:'Landmark and directions',province_context:'Province context'};
  for(const [section,title] of Object.entries({identity:'Applicant profile',personal:'Personal information',spouse:'Spouse information',additional:'Additional personal information',service_location:'Proposed service location'})){const details=document.createElement('details'),summary=document.createElement('summary'),dl=document.createElement('dl');details.open=true;summary.textContent=title;details.append(summary,dl);for(const [field,value] of Object.entries(snapshot[section]??{})){if(!labels[field])continue;const dt=document.createElement('dt'),dd=document.createElement('dd');dt.textContent=labels[field];let shown=value;if(field==='education_code'&&value!==null)shown=[...get('additional.education_code').options].find(o=>o.value===value)?.textContent??value;if(field==='gender')shown={male:'Male',female:'Female',prefer_not_to_say:'Prefer not to say'}[value]??value;if(field==='civil_status'&&value)shown=value[0].toUpperCase()+value.slice(1);dd.textContent=shown===null?'Not provided':String(shown);dl.append(dt,dd);}if(section==='service_location'){const p=document.createElement('p');p.textContent='Artificial test point: '+snapshot.service_location.pin.latitude+', '+snapshot.service_location.pin.longitude+'. Synthetic only; not real service eligibility.';details.append(p);}target.append(details);}
 }
 async function call(action,extra={}){
  if(busy)return;if(action==='resume'&&ui.isDirty()&&!confirm('Replace unsaved edits with the committed database draft?'))return;
  if(['abandon','convert'].includes(action)&&!confirm(action==='abandon'?'Abandon this synthetic draft? History is retained.':'Convert this v1 draft to a new v2 revision? The original is preserved.'))return;
  if(action==='end-session'&&ui.isDirty()&&!confirm('End session and discard unsaved changes? Saved database drafts remain.'))return;
  if(['save','review','check-proof','abandon'].includes(action)&&!receipt){status('Create or resume a draft first.');return;}
  busy=true;const generation=getGeneration();let loadAfter=false;
  try{
   const write=['create','save','abandon','convert'].includes(action);const command={csrf:cfg.csrf,version:cfg.version,action,...(receipt?{reference:receipt.reference,expected:expected()}:{}),...(action==='save'||action==='validate'?{input:read()}:{}),...(write?{key:key()}:{}),...(['abandon','convert'].includes(action)?{confirmed:true}:{}),...(action==='check-proof'?{proof,confirmed:get('confirmations.information_confirmed').checked}:{}),...extra};
   const bytes=action==='retry'?pending?.bytes:JSON.stringify(command);if(!bytes)return;if(write)pending={bytes,action};const operation=action==='retry'?pending.action:action;
   const {r,data}=await request(bytes);pending=null;retry.hidden=true;
   if(r.status===401){clear();status('Session expired. Sign in again and list saved drafts. No command will replay.');const link=document.createElement('a');link.href='/login';link.textContent=' Sign in to recover saved drafts';get('v2-status').append(link);setDirty(false);return;}
   if(!r.ok){if([403,409].includes(r.status))clear();feedback(data);status(data.message??'Request rejected. No partial draft change.');return;}
   if(operation==='end-session'){setDirty(false);location.href='/login';return;}
   if(operation==='list'){const target=get('v2-draft-list');target.replaceChildren();for(const draft of data.drafts){const b=document.createElement('button');b.type='button';b.textContent=(Number(draft.draft_schema)===1?'Convert v1: ':'Resume v2: ')+draft.public_reference;b.onclick=()=>{receipt={reference:draft.public_reference,application_version:Number(draft.lock_version),revision_id:Number(draft.revision_id),revision_no:Number(draft.revision_no),revision_sha256:draft.revision_sha256};call(Number(draft.draft_schema)===1?'convert':'resume');};target.append(b);}status('Only your active synthetic drafts are listed.');return;}
   remember(data);updateProfile(data);
   if(generation!==getGeneration()){clear();status('Request completed but newer edits remain unsaved. Resume explicitly if needed.');return;}
   feedback(data);if(data.input){cfg.profile_fingerprint=data.profile_fingerprint;apply(data.input);setDirty(false);clear();}
   if(operation==='review'){proof=data.proof;render(data.snapshot);show(3);}if(operation==='profile-change')clear();
   if(operation==='abandon'){receipt=null;clear();setDirty(false);get('v2-draft-list').replaceChildren();}
   if(operation==='save'){clear();setDirty(false);}if(operation==='create'||operation==='convert')loadAfter=true;
   status(data.message??'Database action completed.');
  }catch{status('Connection interrupted; outcome may be uncertain. Use exact retry or resume; nothing is auto-submitted.');retry.hidden=!pending;}
  finally{busy=false;}
  if(loadAfter)await call('resume');
 }
 for(const [id,action] of Object.entries({'v2-create':'create','v2-list':'list','v2-abandon':'abandon','v2-end-session':'end-session'}))get(id).onclick=()=>call(action);
 retry.onclick=()=>call('retry');get('v2-save').textContent='Save current section / draft to disposable database';get('v2-resume').textContent='Resume / refresh committed draft';get('v2-review').textContent='Review committed saved information';
 return {call,changed:clear};
};
