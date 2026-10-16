(() => {
    const modal=document.getElementById('ebills-dialog');if(!modal)return;
    const find=name=>modal.querySelector(`[data-ebills-${name}]`);
    const form=find('form'),status=find('status'),summary=find('summary'),review=find('review'),token=find('token'),termsToken=find('terms-token'),version=find('version'),terms=find('terms'),consent=find('consent'),confirm=find('confirm'),cancel=find('cancel'),next=find('next'),back=find('back'),progress=find('progress'),email=find('email');
    const fields=Array.from(modal.querySelectorAll('[data-ebills-personal]')),screens=Array.from(modal.querySelectorAll('[data-ebills-screen]'));
    let ready=false,busy=false,read=false,accepted=false,step=1,request=0,trigger,activeCode='';
    const validate=()=>{
        consent.disabled=!ready||busy||!read;
        next.disabled=!ready||busy||(step===1&&(!read||!consent.checked));
        confirm.disabled=!ready||busy||step!==3||!accepted||!consent.checked;
        back.disabled=cancel.disabled=busy;fields.forEach(field=>field.disabled=busy);
    };
    const show=number=>{
        step=number;screens.forEach(screen=>screen.hidden=Number(screen.dataset.ebillsScreen)!==number);
        summary.hidden=number===1;
        progress.textContent=`Step ${number} of 3: ${['Terms and Conditions','Enrollment Information','Review and Confirmation'][number-1]}`;
        next.hidden=number===3;confirm.hidden=number!==3;back.hidden=number===1;
        next.textContent=number===1?'Continue':'Continue to Review';validate();
        document.getElementById('ebills-title').focus({preventScroll:true});modal.scrollTop=0;
    };
    const append=(container,label,value)=>{
        const box=document.createElement('div'),dt=document.createElement('dt'),dd=document.createElement('dd');
        dt.textContent=label;dt.className='text-slate-500 dark:text-slate-400';dd.textContent=value||'—';dd.className='break-words';box.append(dt,dd);container.append(box);
    };
    const payload=action=>{const data=new FormData(form);data.set('action',action);data.set('consent',consent.checked?'1':'0');return data;};
    const call=async data=>{
        let response,result;
        try{response=await fetch('dashboard.php?page=account-settings',{method:'POST',body:data,credentials:'same-origin',headers:{Accept:'application/json'},cache:'no-store'});result=await response.json();}
        catch(error){throw Error('We could not complete this request. Reopen enrollment to check your session and enrollment status before retrying.');}
        if(!response.ok||!result.ok)throw Error(result.message||'eBills enrollment is temporarily unavailable. Please try again.');return result;
    };
    const checkScroll=()=>{if(ready&&terms.clientHeight>0&&terms.scrollTop+terms.clientHeight>=terms.scrollHeight-2){read=true;validate();}};
    terms.addEventListener('scroll',checkScroll);
    consent.addEventListener('change',()=>{if(!consent.checked)accepted=false;validate();});
    document.addEventListener('click',async event=>{
        const button=event.target.closest('[data-ebills-open]');if(!button||busy||modal.open)return;
        trigger=button;ready=read=accepted=false;form.reset();cancel.textContent='Cancel';token.value=button.dataset.accountToken;termsToken.value=version.value='';
        summary.replaceChildren();review.replaceChildren();status.textContent='Loading official terms and account information…';modal.showModal();show(1);terms.scrollTop=0;const id=++request;
        try{
            const result=await call(payload('ebills_details'));if(id!==request||!modal.open)return;
            const d=result.details;activeCode=d.account.AcctCode;
            append(summary,'PALECO Account Code',d.account.AcctCode);append(summary,'Account Name',d.account.Name);append(summary,'Service Address',d.account.Address);
            fields.forEach(field=>field.value=d.profile[field.name]||'');email.value=d.profile.email||'';
            termsToken.value=d.terms_token;version.value=d.terms_version;
            ready=!d.enrolled&&!d.problem&&version.value===terms.dataset.version;
            status.textContent=d.enrolled?'Already Enrolled in eBills. This PALECO account is already registered for eBills.':d.problem||(ready?'Read and accept the official terms to continue.':'The terms have changed. Refresh this page before enrolling.');
            validate();checkScroll();
        }catch(error){if(id===request){status.textContent=error.message;status.focus();}}
    });
    next.addEventListener('click',async()=>{
        if(!ready||busy)return;
        if(step===1){
            if(!read||!consent.checked)return;
            const data=payload('ebills_accept_terms');busy=true;validate();
            try{await call(data);accepted=true;status.textContent='Enter and review your personal information.';show(2);}
            catch(error){status.textContent=error.message;status.focus();}
            finally{busy=false;validate();}
        }else if(step===2&&accepted){
            if(!fields.every(field=>field.reportValidity()))return;
            if(fields.some(field=>field.required&&!field.value.trim())){status.textContent='Please enter your first name, last name and contact number.';status.focus();return;}
            review.replaceChildren();fields.forEach(field=>append(review,{'first_name':'First Name','last_name':'Last Name','middle_name':'Middle Name','extension_name':'Name Extension','contact_number':'Contact Number'}[field.name],field.value.trim()));
            append(review,'Registered Email',email.value);status.textContent='Review these details before confirming enrollment.';show(3);
        }
    });
    back.addEventListener('click',()=>{if(!busy&&step>1){status.textContent='';show(step-1);}});
    cancel.addEventListener('click',()=>{if(!busy)modal.close();});
    modal.addEventListener('cancel',event=>{if(busy)event.preventDefault();});
    modal.addEventListener('close',()=>{request++;ready=accepted=read=false;token.value=termsToken.value=version.value='';trigger?.focus();});
    form.addEventListener('submit',async event=>{
        event.preventDefault();if(!ready||busy||step!==3||!accepted||!consent.checked)return;
        if(!fields.every(field=>field.checkValidity())){show(2);return;}
        const data=payload('ebills_enroll');busy=true;validate();status.textContent='Submitting enrollment…';
        try{
            const result=await call(data);ready=false;status.textContent=result.message;status.focus();cancel.textContent='Close';next.hidden=back.hidden=confirm.hidden=true;
            document.querySelectorAll('[data-ebills-open]').forEach(button=>{if(button.dataset.ebillsCode===activeCode){const badge=document.createElement('span');badge.textContent='eBills Enrolled';badge.className='mt-3 inline-block rounded-lg bg-green-50 px-3 py-2 text-sm font-semibold text-green-700 dark:bg-green-900/30 dark:text-green-300';button.replaceWith(badge);}});
            if(result.created)fields.forEach(field=>{const saved=document.querySelector(`#profile-settings input[name="${field.name}"]`);if(saved)saved.value=field.value.trim();});
        }catch(error){status.textContent=error.message;status.focus();}
        finally{busy=false;validate();}
    });
})();
