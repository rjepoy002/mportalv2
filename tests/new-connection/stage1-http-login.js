(() => {
 const context=JSON.parse(document.getElementById('login-context').textContent);
 document.getElementById('fixture-login').addEventListener('submit',async e=>{e.preventDefault();try{
  const response=await fetch('/api/login',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({csrf:context.csrf,login:document.getElementById('login').value,password:document.getElementById('password').value}),cache:'no-store'});
  if(!response.ok)throw Error('Synthetic sign-in unsuccessful.');
  location.assign('/app'+(context.reference?'?r='+encodeURIComponent(context.reference):''));
 }catch(error){document.getElementById('login-error').textContent=error.message;}});
})();
