<?php header('Cache-Control: no-store'); ?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login Test</title>
<style>
body{font-family:monospace;background:#0d1a0d;color:#74c69d;padding:1rem;font-size:13px;max-width:500px}
h2{color:#fff;margin:.75rem 0 .35rem}
input{width:100%;padding:.5rem;border-radius:6px;border:1px solid #2d5a2d;background:#0d1a0d;color:#74c69d;font-size:13px;font-family:monospace;margin:.2rem 0}
button{background:#2d9e68;color:#fff;border:none;padding:.5rem 1rem;border-radius:6px;cursor:pointer;font-size:13px;width:100%;margin:.2rem 0}
.box{background:#1a2e1a;border:1px solid #2d5a2d;border-radius:8px;padding:.75rem;margin:.4rem 0;white-space:pre-wrap;word-break:break-all;font-size:.78rem}
.ok{color:#74c69d}.err{color:#ff6b6b}.warn{color:#ffd166}
</style>
</head><body>
<h1 style="color:#fff">Login + Admin Test</h1>

<h2>1. Ping API</h2>
<button onclick="testPing()">Probar ping</button>
<div id="r-ping" class="box warn">...</div>

<h2>2. Login</h2>
<input id="email" type="email" placeholder="email">
<input id="pass" type="password" placeholder="password">
<button onclick="testLogin()">Login</button>
<div id="r-login" class="box warn">...</div>

<h2>3. Check Admin</h2>
<input id="tok2" placeholder="token (se llena auto)">
<button onclick="testAdmin()">Verificar admin</button>
<div id="r-admin" class="box warn">...</div>

<script>
var API='https://m3cplus.com/wp-json/vk/v1';
async function go(url,opts,id){
  var el=document.getElementById(id);el.textContent='...';el.className='box warn';
  try{var r=await fetch(url,opts||{});var t=await r.text();var d;try{d=JSON.parse(t);}catch(e){d=t;}
  el.className=r.ok?'box ok':'box err';el.textContent='HTTP '+r.status+'\n\n'+(typeof d==='string'?d:JSON.stringify(d,null,2));return{ok:r.ok,data:d};}
  catch(e){el.className='box err';el.textContent='ERROR: '+e.message;return null;}
}
async function testPing(){await go(API+'/ping',{},'r-ping');}
async function testLogin(){
  var em=document.getElementById('email').value.trim();var pw=document.getElementById('pass').value.trim();
  if(!em||!pw){document.getElementById('r-login').textContent='Completa ambos campos';return;}
  var res=await go(API+'/login',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({username:em,password:pw})},'r-login');
  if(res&&res.ok&&res.data&&res.data.token){
    document.getElementById('tok2').value=res.data.token;
    localStorage.setItem('vk_tok',JSON.stringify(res.data.token));
    document.getElementById('r-login').textContent+='\n\nToken guardado en localStorage';
  }
}
async function testAdmin(){
  var tok=document.getElementById('tok2').value.trim();
  if(!tok){document.getElementById('r-admin').textContent='Necesitas token';return;}
  await go(API+'/check-admin?vk_token='+encodeURIComponent(tok),{},'r-admin');
}
window.addEventListener('load',function(){
  try{var raw=localStorage.getItem('vk_tok');var tok=null;try{tok=JSON.parse(raw);}catch(e){tok=raw;}
  if(tok&&typeof tok==='string')document.getElementById('tok2').value=tok;}catch(e){}
  testPing();
});
</script>
</body></html>
