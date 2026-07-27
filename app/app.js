/*  ENCUESTAS (parte 1)  */
/*  ENCUESTAS  */
var _pollAnswers = {};
var _currentPollId = 0;
var _pollVoted = {};

function escPoll(v){return String(v==null?'':v).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}
function pollImg(p){return p.featured_image_full||p.featured_image_large||p.featured_image||p.image||p.thumbnail||'';}

async function loadPolls(){
  var listEl=document.getElementById('polls-list');
  var sumEl=document.getElementById('polls-summary');
  if(!listEl)return;
  listEl.innerHTML='<div class="spinner-wrap"><div class="spinner"></div>Cargando encuestas...</div>';
  if(sumEl)sumEl.innerHTML=' Cargando...';
  try{
    var d=await getJSON(C.API_BASE + '/vk/v1/polls');
    var list=(d&&d.data)?d.data:[];
    if(sumEl)sumEl.innerHTML=' '+list.length+' encuesta'+(list.length===1?' disponible':'s disponibles');
    if(!list.length){
      listEl.innerHTML='<div class="poll-empty"><div class="poll-empty-icon"><i class="fas fa-pen-to-square"></i></div><h3 style="color:var(--vk-plum);font-size:1.35rem;margin-bottom:.35rem">No hay encuestas disponibles</h3><p>Cuando publiquemos nuevas encuestas aparecerán aquí.</p></div>';
      return;
    }
    listEl.innerHTML='<div class="polls-list">'+list.map(renderPollCard).join('')+'</div>';
  }catch(e){
    listEl.innerHTML='<div class="error-card"><h4>Error</h4><p>No se pudieron cargar las encuestas.</p></div>';
    if(sumEl)sumEl.innerHTML=' Error';
  }
}

function renderPollCard(p){
  var id=p.id||p.ID;
  var name=escPoll(p.name||p.title||'Encuesta');
  var desc=escPoll(p.description||'Participa y comparte tu opinion.');
  var status=p.status||'active';
  var active=(status==='active'||status==='published');
  var votes=Number(p.total_votes||0);
  var questions=Number(p.questions_count||p.total_questions||0);
  var voted=!!_pollVoted[id];
  var lname=(name||'').toLowerCase();
  var icon='fa-clipboard-list';
  if(lname.indexOf('satisfac')>=0||lname.indexOf('experienc')>=0) icon='fa-star';
  else if(lname.indexOf('tema')>=0||lname.indexOf('interes')>=0||lname.indexOf('nuevo')>=0) icon='fa-lightbulb';
  else if(lname.indexOf('plataform')>=0||lname.indexOf('mejora')>=0||lname.indexOf('feedback')>=0) icon='fa-cog';
  else if(lname.indexOf('evento')>=0||lname.indexOf('taller')>=0) icon='fa-calendar-alt';
  else if(lname.indexOf('curso')>=0||lname.indexOf('aprend')>=0) icon='fa-graduation-cap';
  var metaStr=questions?questions+' preguntas':(votes?votes+' respuestas':'');
  return '<article class="poll-row" onclick="openPoll('+id+')">'    +'<div class="poll-row-icon"><i class="fas '+icon+'"></i></div>'    +'<div class="poll-row-body">'    +'<h3 class="poll-row-title">'+name+'</h3>'    +'<p class="poll-row-desc">'+desc+'</p>'    +'</div>'    +(metaStr?'<span class="poll-row-meta">'+metaStr+'</span>':'')    +'<button class="poll-row-btn'+(voted?' poll-row-btn--done':'')+'" onclick="event.stopPropagation();openPoll('+id+')">'+(voted?'✅ Ya vote':'Responder')+'</button>'    +'</article>';
}

async function openPoll(id){
  _currentPollId=id;_pollAnswers={};
  goto('poll-detail');
  var short=document.getElementById('poll-title-short');
  if(short)short.textContent='Cargando...';
  var body=document.getElementById('poll-detail-body');
  if(body)body.innerHTML='<div class="spinner-wrap"><div class="spinner"></div>Cargando encuesta...</div>';
  try{
    var d=await getJSON(C.API_BASE + '/vk/v1/polls/' + id);
    if(!d||!(d.name||d.title)){
      body.innerHTML='<div class="error-card"><h4>Error</h4><p>No se pudo cargar la encuesta.</p></div>';return;
    }
    if(short)short.textContent=(d.name||d.title||'Encuesta').substring(0,22);
    renderPoll(d);
  }catch(e){
    body.innerHTML='<div class="error-card"><h4>Error</h4><p>No se pudo cargar la encuesta.</p></div>';
  }
}

function renderPoll(poll){
  var voted=!!_pollVoted[poll.id];
  var isActive=poll.status==='active'||poll.status==='published'||!poll.status;
  var questions=poll.questions||[];
  var name=escPoll(poll.name||poll.title||'Encuesta');
  var desc=escPoll(poll.description||'');
  var html='<div class="poll-detail-head"><h2>'+name+'</h2>'+(desc?'<p>'+desc+'</p>':'')+'<p> '+Number(poll.total_votes||0)+' respuestas totales</p></div>';
  if(!questions.length){
    html+='<div class="poll-empty"><div class="poll-empty-icon"></div><p>Esta encuesta no tiene preguntas disponibles.</p></div>';
    document.getElementById('poll-detail-body').innerHTML=html;return;
  }
  questions.forEach(function(q,qi){
    var qid=q.id||q.ID;
    var totalQ=q.total_votes||1;
    html+='<section class="poll-question"><p class="poll-question-title">'+(qi+1)+'. '+escPoll(q.text||q.title||'Pregunta')+'</p>';
    if(q.multiple)html+='<p class="poll-help">Puedes elegir varias opciones</p>';
    if(q.is_text){
      if(voted||!isActive){html+='<p style="font-size:.88rem;color:var(--ts);font-style:italic">Pregunta de texto libre</p>';}
      else{html+='<textarea class="poll-textarea" id="ptxt-'+qid+'" placeholder="Escribe tu respuesta..." onchange="_pollAnswers['+qid+']=[this.value]"></textarea>';}
      html+='</section>';return;
    }
    (q.options||[]).forEach(function(opt){
      var oid=opt.id||opt.ID;
      var pct=totalQ>0?Math.round(((opt.votes||0)/totalQ)*100):0;
      var selected=_pollAnswers[qid]&&_pollAnswers[qid].indexOf(oid)>=0;
      if(voted||!isActive){
        html+='<div class="poll-result"><div class="poll-result-row"><span>'+escPoll(opt.text||opt.title||'Opción')+'</span><span class="poll-result-pct">'+pct+'%</span></div><div class="poll-result-bar"><div class="poll-result-fill" style="width:'+pct+'%"></div></div><p class="poll-result-votes">'+Number(opt.votes||0)+' votos</p></div>';
      }else{
        html+='<div onclick="togglePollAnswer('+qid+','+oid+','+(q.multiple?1:0)+')" id="popt-'+qid+'-'+oid+'" class="poll-option '+(selected?'is-selected':'')+'"><div class="poll-box '+(q.multiple?'multi':'')+'">'+(selected?'✅':'')+'</div><span>'+escPoll(opt.text||opt.title||'Opción')+'</span></div>';
      }
    });
    html+='</section>';
  });
  if(!voted&&isActive)html+='<div id="poll-submit-wrap" style="margin-top:.5rem"><div id="poll-error-msg" style="display:none" class="vk-poll-msg vk-poll-msg--error"></div><button onclick="submitPoll('+poll.id+')" id="btn-submit-poll" class="btn btn-primary" style="width:100%">Enviar respuestas</button></div>';
  else if(!isActive)html+='<div class="poll-status closed">Encuesta cerrada</div>';
  else html+='<div class="vk-poll-msg vk-poll-msg--voted"> Ya has votado en esta encuesta. ¡Gracias por tu participación!</div>';
  document.getElementById('poll-detail-body').innerHTML=html;
}

function togglePollAnswer(qId,optId,multiple){
  if(!_pollAnswers[qId])_pollAnswers[qId]=[];
  var idx=_pollAnswers[qId].indexOf(optId);
  if(multiple){if(idx>=0)_pollAnswers[qId].splice(idx,1);else _pollAnswers[qId].push(optId);}else{_pollAnswers[qId]=[optId];}
  var all=document.querySelectorAll('[id^="popt-'+qId+'-"]');
  all.forEach(function(el){
    var parts=el.id.split('-');var oid=parseInt(parts[parts.length-1],10);
    var sel=_pollAnswers[qId].indexOf(oid)>=0;
    el.classList.toggle('is-selected',sel);
    var box=el.querySelector('.poll-box');if(box)box.innerHTML=sel?'✅':'';
  });
}

async function submitPoll(pollId){
  if(Object.keys(_pollAnswers).length===0){
    var errEl=document.getElementById('poll-error-msg');
    if(errEl){errEl.textContent=' Por favor selecciona al menos una respuesta.';errEl.style.display='flex';}
    else toast('Selecciona al menos una respuesta');
    return;
  }
  var errEl=document.getElementById('poll-error-msg');
  if(errEl)errEl.style.display='none';
  var answers=Object.keys(_pollAnswers).map(function(qId){return {question_id:parseInt(qId,10),answer_ids:_pollAnswers[qId]};});
  var body={answers:answers};
  var tok=ST.token||S.get('vk_token')||'';if(tok)body.vk_token=tok;
  var btn=document.getElementById('btn-submit-poll');
  if(btn){btn.disabled=true;btn.textContent='Enviando...';btn.style.opacity='.7';}
  try{
    var r=await fetch(C.API_BASE + '/vk/v1/polls/' + pollId + '/vote',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
    var d=await r.json();
    if(r.ok&&d.success){
      _pollVoted[pollId]=true;
      document.getElementById('poll-detail-body').innerHTML='<div class="vk-poll-success"><div class="vk-poll-success-icon">✅</div><h3>¡Voto registrado!</h3><p>Tu respuesta fue enviada correctamente. ¡Gracias por participar!</p><button onclick="goto(\'polls\')" class="btn btn-primary" style="margin-top:1.25rem;max-width:240px">Ver más encuestas</button></div>';
    }
    else if(d&&d.code==='already_voted'){
      _pollVoted[pollId]=true;
      document.getElementById('poll-detail-body').innerHTML='<div class="vk-poll-success"><div class="vk-poll-success-icon" style="font-size:2.5rem"> </div><h3>Ya has votado</h3><p>Ya registraste tu respuesta en esta encuesta anteriormente.</p><button onclick="goto(\'polls\')" class="btn btn-outline" style="margin-top:1.25rem;max-width:240px">Ver encuestas</button></div>';
    }
    else{if(btn){btn.disabled=false;btn.textContent='Enviar respuestas';btn.style.opacity='';}var e2=document.getElementById('poll-error-msg');if(e2){e2.textContent=' '+((d&&d.message)||'Error al enviar. Intenta de nuevo.');e2.style.display='flex';}else toast((d&&d.message)||'Error al enviar');}
  }catch(e){
    if(btn){btn.disabled=false;btn.textContent='Enviar respuestas';btn.style.opacity='';}
    var e3=document.getElementById('poll-error-msg');
    if(e3){e3.textContent=' Error de conexión. Verifica tu internet.';e3.style.display='flex';}else toast('Error de conexión');
  }
}


/*  RESPONSIVE CHROME PATCH  */
/* =========================================================
   RESPONSIVE CHROME PATCH FINAL
   Mantiene sidebar solo en escritorio y bottom nav solo en móvil.
   ========================================================= */
(function(){
  function m3cApplyChrome(){
    var logged=document.body.classList.contains('is-logged-in');
    var desktop=window.matchMedia('(min-width:1023px)').matches;
    var sidebar=document.getElementById('desktop-sidebar');
    var bottom=document.getElementById('bottom-nav');
    if(sidebar) sidebar.style.display=(logged&&desktop)?'flex':'none';
    if(bottom) bottom.style.display=(logged&&!desktop)?'flex':'none';
    if(desktop){
      var c=document.getElementById('course-cat-panel'), cb=document.getElementById('course-cat-backdrop');
      var p=document.getElementById('product-cat-panel'), pb=document.getElementById('product-cat-backdrop');
      if(c)c.classList.remove('open'); if(cb)cb.classList.remove('open');
      if(p)p.classList.remove('open'); if(pb)pb.classList.remove('open');
    }
  }
  window.addEventListener('resize',m3cApplyChrome);
  window.addEventListener('orientationchange',m3cApplyChrome);
  document.addEventListener('DOMContentLoaded',m3cApplyChrome);
  var oldEnter=window.enterApp;
  if(typeof oldEnter==='function'){
    window.enterApp=function(){ oldEnter.apply(this,arguments); setTimeout(m3cApplyChrome,0); };
  }
  var oldLogout=window.logout;
  if(typeof oldLogout==='function'){
    window.logout=function(){ oldLogout.apply(this,arguments); setTimeout(m3cApplyChrome,0); };
  }
  window.m3cApplyChrome=m3cApplyChrome;
})();


/*  APP PRINCIPAL  */
/*  CONFIG  */
var isLocal = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1' || window.location.hostname.includes('.local') || /^(192\.168\.|10\.|172\.(1[6-9]|2[0-9]|3[0-1])\.)/.test(window.location.hostname);
var C = {
  API_BASE: (window.C && window.C.API_BASE) ? window.C.API_BASE : (isLocal ? window.location.origin + '/wp/vk/wp-json' : 'https://vidakushala.com/wp-json'),
  WP_URL: (window.C && window.C.SITE_BASE) ? window.C.SITE_BASE : (isLocal ? window.location.origin + '/wp/vk' : 'https://vidakushala.com'),
  WA_NUM: '5213122255374'
};
var S = {
  get:function(k){try{return JSON.parse(localStorage.getItem(k));}catch(e){return null;}},
  set:function(k,v){localStorage.setItem(k,JSON.stringify(v));},
  del:function(k){localStorage.removeItem(k);}
};
var _SD = null;
var SS = {
  save:function(d){_SD=d;try{localStorage.setItem('_vk_sd',JSON.stringify(d));}catch(e){}},
  load:function(){if(_SD)return _SD;try{var d=localStorage.getItem('_vk_sd');if(d){_SD=JSON.parse(d);return _SD;}}catch(e){}return null;},
  clear:function(){_SD=null;try{localStorage.removeItem('_vk_sd');}catch(e){}}
};
var ST = {user:null,token:null,courses:[],cur:null,lesson:null};
var _quiz=null,_quizAnswers={},_publicCourse=null;
var _isSocialReg=false;
var _homePreviewPromise = null;

/*  RESPONSIVE INIT  */
function applyResponsiveLogin(){
  var isDesktop=window.innerWidth>=1025;
  var left=document.getElementById('login-left');
  var mlogo=document.getElementById('login-mobile-logo');
  var dhead=document.getElementById('login-desktop-heading');
  var loginEl=document.getElementById('screen-login');
  if(isDesktop){
    if(left){left.style.display='flex';}
    if(mlogo)mlogo.style.display='none';
    if(dhead)dhead.style.display='block';
    if(loginEl)loginEl.style.flexDirection='row';
  } else {
    if(left){left.style.display='none';}
    if(mlogo)mlogo.style.display='block';
    if(dhead)dhead.style.display='none';
    if(loginEl){loginEl.style.flexDirection='column';loginEl.style.background='linear-gradient(160deg,#fce8f1,#fdf6f9,#fef0f7)';}
  }
}
window.addEventListener('resize',applyResponsiveLogin);

/*  SIDEBAR ACTIVE  */
function updateSidebarActive(name){
  document.querySelectorAll('.snav-item').forEach(function(el){el.classList.remove('active');});
  var el=document.getElementById('snav-'+name);
  if(el)el.classList.add('active');
}

/*  ARRANQUE  */
document.addEventListener('DOMContentLoaded',function(){
  document.body.classList.add('is-logged-out');
  applyResponsiveLogin();
  var params=new URLSearchParams(window.location.search);
  
  // Guardar deep links antes de limpiar la URL
  var paramCourse = params.get('open_course');
  var paramProduct = params.get('open_product');
  var paramPoll = params.get('open_poll');
  var paramCert = params.get('open_cert');
  var paramBundle = params.get('open_bundle');
  if (paramCourse || paramProduct || paramPoll || paramCert || paramBundle) {
    window.VK_DEEP_LINK = {
      course: paramCourse,
      product: paramProduct,
      poll: paramPoll,
      cert: paramCert,
      bundle: paramBundle
    };
  }

  var sToken=params.get('vk_login');
  // Manejar activacion de email via enlace
  // Activacion via PHP server-side (más confiable)
  var activateToken=params.get('activate');
  if(activateToken){ handleEmailActivation(activateToken); }

  // Deep link desde correo de inscripcion: ?course=ID
  var courseParam = params.get('course');
  if(courseParam){ window._pendingCourseOpen = parseInt(courseParam); }

  // Deep link desde correo de certificado: ?cert=HASH
  var certParam = params.get('cert');
  if(certParam){ window._pendingCertOpen = certParam; }

  // Leer resultado de activacion inyectado por PHP
  if(window.VK_ACTIVATION && window.VK_ACTIVATION.activated){
    processPhpActivation(window.VK_ACTIVATION);
  } else if(window.VK_ACTIVATION && window.VK_ACTIVATION.error){
    setTimeout(function(){
      showToast(window.VK_ACTIVATION.error||'Error al activar cuenta.');
      goto('login');
    },400);
  }
  if(sToken){
    window.history.replaceState({},'',window.location.pathname);
    var uid=parseInt(params.get('uid')||'0');
    var name=decodeURIComponent(params.get('name')||'Estudiante');
    var email=decodeURIComponent(params.get('email')||'');
    var avatar=decodeURIComponent(params.get('avatar')||'');
    ST.token=sToken;ST.user={id:uid,name:name,email:email,avatar:avatar};
    S.set('vk_token',sToken);S.set('vk_user',ST.user);
    hideSplash();showToast('✅ ¡Bienvenido, '+name+'!');setTimeout(enterApp,300);return;
  }
  var tok=S.get('vk_token'),usr=S.get('vk_user');
  hideSplash();
  if(tok&&usr&&usr.id){
    ST.token=tok;
    ST.user=usr;
    // Ensure avatar is refreshed if missing from stored user data
    if(!ST.user.avatar){
      fetch(C.API_BASE+'/vk/v1/verify?vk_token='+encodeURIComponent(tok))
        .then(function(r){return r.json();})
        .then(function(d){
          if(d&&d.avatar_url){ST.user.avatar=d.avatar_url;S.set('vk_user',ST.user);}
          enterApp();
        })
        .catch(function(){enterApp();});
    } else {
      enterApp();
    }
  }
  else{goto('login');}
});

function hideSplash(){
  var s=document.getElementById('splash');
  if(!s)return;
  s.style.transition='opacity .35s';s.style.opacity='0';
  setTimeout(function(){if(s.parentNode)s.parentNode.removeChild(s);},360);
}

/*  TOAST  */
var _tt;
function toast(msg){ showToast(msg); }
function showToast(msg){
  var t=document.getElementById('toast');
  t.innerHTML=msg;t.classList.add('visible');
  clearTimeout(_tt);_tt=setTimeout(function(){t.classList.remove('visible');},2800);
}

/*  NAVEGACIÓN  */
var _gotoTimeout1 = null;
var _gotoTimeout2 = null;
function goto(name){
  if (_gotoTimeout1) clearTimeout(_gotoTimeout1);
  if (_gotoTimeout2) clearTimeout(_gotoTimeout2);
  // Cerrar menús al navegar (solo si están abiertos)
  var _dd = document.getElementById('mhdr-dropdown');
  if (_dd && _dd.classList.contains('mhdr-open')) {
    if (typeof closeMobileMenu === 'function') closeMobileMenu();
  }
  var _dtb = document.getElementById('dtb-dropdown');
  if (_dtb && _dtb.classList.contains('dtb-open')) {
    if (typeof closeDtbMenu === 'function') closeDtbMenu();
  }
  // AI Chat: verificar acceso antes de navegar
  if (name === 'chat') { openAiChat(); return; }

  // Al salir del chat restaurar bottom-nav
  var activeSc = document.querySelector('.screen.active');
  if (activeSc && activeSc.id === 'screen-chat') {
    var bnav = document.getElementById('bottom-nav');
    if (bnav && window.innerWidth < 1025) bnav.style.display = 'flex';
  }

  // Limpiar preventivamente active/exit de todas las pantallas para evitar estados duplicados
  document.querySelectorAll('.screen').forEach(function(s){
    if (s.id !== 'screen-' + name) {
      s.classList.remove('active', 'exit');
    }
  });

  var activeScreen = document.querySelector('.screen.active');
  if (activeScreen && activeScreen.id !== 'screen-' + name) {
    activeScreen.classList.add('exit');
    var prevScreen = activeScreen;
    _gotoTimeout1 = setTimeout(function(){
      prevScreen.classList.remove('active', 'exit');
    }, 300);
  }

  _gotoTimeout2 = setTimeout(function(){
    var el = document.getElementById('screen-' + name);
    if(el) {
      el.classList.remove('exit');
      el.classList.add('active');
    }
  }, 50);

  // Bottom nav: en pantallas de detalle mantiene activo el botón principal
  var activeNav=name;
  if(['course-detail','lesson','quiz'].indexOf(name)>=0) activeNav='courses';
  if(['public-course'].indexOf(name)>=0) activeNav='search';
  if(['product-detail'].indexOf(name)>=0) activeNav='products';
  if(['poll-detail'].indexOf(name)>=0) activeNav='polls';
  if(['bundle-detail'].indexOf(name)>=0) activeNav='courses';
  if(name==='directory-profile') activeNav='directory';
  ['home','courses','products','polls','search','profile'].forEach(function(n){
    var el=document.getElementById('nav-'+n);
    if(el)el.classList.toggle('active',n===activeNav);
  });
  updateSidebarActive(activeNav);
  if(window.m3cApplyChrome)setTimeout(window.m3cApplyChrome,0);
  // Mostrar/ocultar banner PWA solo en home
  var _pwaBanner = document.getElementById('vk-pwa-banner');
  if (_pwaBanner && _pwaBanner.dataset.built === '1' && !_pwaBanner.dataset.dismissed) {
    _pwaBanner.style.display = (name === 'home') ? 'flex' : 'none';
  }
  if(name==='courses')loadCourses();
  if(name==='profile')loadProfile();
  if(name==='search')loadAllCourses();
  if(name==='products')loadProducts();
  if(name==='polls')loadPolls();
  if(name==='documents')loadDocuments();
  if(name==='directory-profile')loadDirectoryProfile();
  if(name==='qa')vkQA.loadFeed();
  if(name==='bundles')loadBundles();
  if(name==='certificates')loadCertificates();
  if(name==='settings')loadSettings();
  if(name==='notifications')loadNotifications();
  if(name==='home') setTimeout(loadHomeNotifications, 300);
  // Abrir curso desde deep link de correo
  if(name==='home' && window._pendingCourseOpen) {
    var cid = window._pendingCourseOpen;
    delete window._pendingCourseOpen;
    window.history.replaceState({}, '', '/');
    setTimeout(function(){ openCourse(cid); }, 600);
  }
  // Abrir certificado desde deep link de correo
  if(name==='home' && window._pendingCertOpen) {
    var chash = window._pendingCertOpen;
    delete window._pendingCertOpen;
    window.history.replaceState({}, '', '/');
    setTimeout(function(){
      if(typeof downloadCertificate === 'function') {
        // Buscar el curso por hash en los certificados del usuario
        fetch(apiURL('/vk/v1/my-certificates'))
          .then(function(r){ return r.json(); })
          .then(function(d){
            var certs = d.data || d.certificates || [];
            var cert = certs.find(function(x){ return x.cert_hash === chash; });
            if(cert && cert.course_id) {
              downloadCertificate(cert.course_id);
            } else {
              goto('certificates');
            }
          })
          .catch(function(){ goto('certificates'); });
      } else {
        goto('certificates');
      }
    }, 800);
  }
}

function apiURL(path){
  var tok=ST.token||S.get('vk_token')||'';
  var sep=path.indexOf('?')>=0?'&':'?';
  return C.API_BASE+path+sep+'vk_token='+encodeURIComponent(tok);
}

/* Caché en memoria — evita peticiones repetidas y sirve datos anteriores si falla la API */
var _memCache={};
var _MEM_TTL=90000; // 90 segundos
function _cacheGet(key){ var e=_memCache[key]; return(e&&Date.now()<e.exp)?e.val:null; }
function _cacheSet(key,val,ttl){ _memCache[key]={val:val,exp:Date.now()+(ttl||_MEM_TTL)}; }
function _cacheDel(key){ delete _memCache[key]; }
async function getCached(url,ttl){
  var hit=_cacheGet(url);
  if(hit)return hit;
  var d=await getJSON(url);
  if(d&&!d._fetchError)_cacheSet(url,d,ttl);
  else{
    var stale=_memCache[url];
    if(stale)return stale.val;
  }
  return d;
}
/* Fetch con reintentos automáticos y timeout */
async function _fetchWithRetry(url, opts, retries){
  retries = retries === undefined ? 2 : retries;
  var timeout = 12000;
  for(var attempt = 0; attempt <= retries; attempt++){
    var ctrl = typeof AbortController !== 'undefined' ? new AbortController() : null;
    var tid = ctrl ? setTimeout(function(){ ctrl.abort(); }, timeout) : null;
    try{
      var r = await fetch(url, Object.assign({}, opts||{}, ctrl ? {signal: ctrl.signal} : {}));
      if(tid) clearTimeout(tid);
      if(!r.ok && r.status >= 500 && attempt < retries){
        await new Promise(function(res){ setTimeout(res, 600 * (attempt + 1)); });
        continue;
      }
      var ct = r.headers.get('content-type') || '';
      if(!ct.includes('json')) return {};
      return await r.json();
    }catch(e){
      if(tid) clearTimeout(tid);
      var isNet = !navigator.onLine || e.name === 'AbortError' || e.message === 'Failed to fetch' || String(e).includes('NetworkError');
      if(isNet && attempt < retries){
        await new Promise(function(res){ setTimeout(res, 800 * (attempt + 1)); });
        continue;
      }
      return {_fetchError: true, _offline: isNet};
    }
  }
  return {_fetchError: true};
}
async function getJSON(url){return _fetchWithRetry(url);}
async function postJSON(url,data,method,extra){
  var h=Object.assign({'Content-Type':'application/json'},extra||{});
  return _fetchWithRetry(url,{method:method||'POST',headers:h,body:JSON.stringify(data)});
}

/* Genera el mensaje de WhatsApp con datos del curso y del usuario autenticado */
function buildWaEnrollMsg(courseTitle,price){
  var u=ST.user||{};
  var name=(u.name||'').trim();
  var email=(u.email||'').trim();
  var now=new Date();
  var dateStr=now.toLocaleDateString('es-MX',{day:'2-digit',month:'long',year:'numeric'})
    +' a las '+now.toLocaleTimeString('es-MX',{hour:'2-digit',minute:'2-digit'});
  var msg='Hola, deseo inscribirme en el siguiente curso:\n\n'
    +'📚 *Curso:* '+courseTitle+'\n'
    +(price?'💰 *Precio:* '+price+'\n':'')
    +'\n'
    +(name?'👤 *Nombre:* '+name+'\n':'')
    +(email?'📧 *Correo:* '+email+'\n':'')
    +'📅 *Fecha:* '+dateStr
    +'\n\nSolicito información para completar mi inscripción. Quedo atento/a a su respuesta.\n\n_Gracias._';
  return msg;
}
function buildWaLink(msg){return 'https://wa.me/'+C.WA_NUM+'?text='+encodeURIComponent(msg);}

/*  FACEBOOK SDK  */
window.fbAsyncInit=function(){
  FB.init({appId:'2155344185383534',cookie:true,xfbml:true,version:'v21.0'});
  FB.AppEvents.logPageView();
  FB.getLoginStatus(function(response){
    if(response.status==='connected'){
      // Ya autenticado: cargar perfil y personalizar el botón
      FB.api('/me',{fields:'name,first_name,picture.width(80)'},function(me){
        _fbUpdateButtons(me);
        // Auto-login solo si no hay sesión activa Y el usuario no cerró sesión manualmente
        if(!ST.token&&!S.get('vk_token')&&!localStorage.getItem('_vk_fb_logout')){
          fbHandleLogin(response.authResponse.accessToken,true);
        }
      });
    }
  });
};

/* Personaliza todos los botones de Facebook con foto y nombre del usuario */
function _fbUpdateButtons(me){
  if(!me||!me.name)return;
  var avatar=(me.picture&&me.picture.data&&me.picture.data.url)||'';
  var name=me.first_name||me.name.split(' ')[0]||'';
  var html='<div style="display:flex;align-items:center;gap:.55rem;pointer-events:none">'
    +(avatar?'<img src="'+avatar+'" style="width:26px;height:26px;border-radius:50%;border:2px solid rgba(255,255,255,.4);object-fit:cover">':'<svg width="20" height="20" viewBox="0 0 24 24" fill="#fff"><path d="M24 12.073C24 5.405 18.627 0 12 0S0 5.405 0 12.073C0 18.1 4.388 23.094 10.125 24v-8.437H7.078v-3.49h3.047V9.41c0-3.025 1.792-4.697 4.533-4.697 1.312 0 2.686.236 2.686.236v2.97h-1.513c-1.491 0-1.956.931-1.956 1.886v2.267h3.328l-.532 3.49h-2.796V24C19.612 23.094 24 18.1 24 12.073z"/></svg>')
    +'<span>Continuar como '+name+'</span>'
    +'</div>';
  document.querySelectorAll('.vk-fb-btn').forEach(function(btn){
    btn.innerHTML=html;
    btn.style.background='#1877f2';
  });
}
(function(d,s,id){
  var js,fjs=d.getElementsByTagName(s)[0];
  if(d.getElementById(id))return;
  js=d.createElement(s);js.id=id;js.src='https://connect.facebook.net/es_LA/sdk.js';
  fjs.parentNode.insertBefore(js,fjs);
}(document,'script','facebook-jssdk'));

function checkFBLoginState(){
  FB.getLoginStatus(function(response){
    if(response.status==='connected'){fbHandleLogin(response.authResponse.accessToken,false);}
    else{showToast(' No se pudo completar el acceso con Facebook');}
  });
}

async function fbHandleLogin(accessToken,silent){
  if(!silent)showToast('  Verificando con Facebook...');
  try{
    var r=await fetch(C.API_BASE+'/vk/v1/facebook-login',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({access_token:accessToken})});
    var d=await r.json();
    if(r.ok){loginSuccess(d);return;}
    if(r.status===404){
      var ex=(d.data&&typeof d.data==='object')?d.data:d;
      _isSocialReg=true;
      _SD={email:ex.email||'',first_name:ex.first_name||'',last_name:ex.last_name||'',avatar:ex.avatar||'',provider:'facebook',access_token:accessToken};
      showCompleteProfile(_SD);return;
    }
    if(!silent)showToast(' '+(d.message||'Error con Facebook'));
  }catch(e){if(!silent)showToast(' Error de conexión');}
}

/*  GOOGLE  */
async function handleGoogleResponse(response){
  if(!response||!response.credential){showToast(' Error Google');return;}
  showToast('  Verificando con Google...');
  try{
    var r=await fetch(C.API_BASE+'/vk/v1/google-login',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({credential:response.credential})});
    var d=await r.json();
    if(r.ok){loginSuccess(d);return;}
    if(r.status===404){
      var ex=(d.data&&typeof d.data==='object')?d.data:d;
      _isSocialReg=true;
      _SD={email:ex.email||'',first_name:ex.first_name||'',last_name:ex.last_name||'',avatar:ex.avatar||'',provider:'google',credential:response.credential};
      showCompleteProfile(_SD);return;
    }
    showToast(' '+(d.message||'Error'));
  }catch(e){showToast(' Error de conexión');}
}

/*  COMPLETAR PERFIL SOCIAL  */
function showCompleteProfile(sd){
  if(!sd)return;
  var modal=document.getElementById('social-register-modal');
  var av=document.getElementById('modal-avatar');
  var f=document.getElementById('modal-first');
  var l=document.getElementById('modal-last');
  if(av&&sd.avatar)av.innerHTML='<img src="'+sd.avatar+'" style="width:100%;height:100%;object-fit:cover;border-radius:50%">';
  if(f)f.value=sd.first_name||'';
  if(l)l.value=sd.last_name||'';
  if(modal)modal.style.display='flex';
  setTimeout(function(){if(f&&!f.value)f.focus();else if(l&&!l.value)l.focus();else if(f)f.select();},150);
}

/*  REGISTRO MANUAL  */
async function registroManual(){
  var first=(document.getElementById('reg-first')||{value:''}).value.trim();
  var last=(document.getElementById('reg-last')||{value:''}).value.trim();
  var email=(document.getElementById('reg-email')||{value:''}).value.trim();
  var pass=(document.getElementById('reg-pass')||{value:''}).value.trim();
  if(!first||!last){showToast(' Escribe tu nombre y apellido');return;}
  if(!email){showToast(' El correo es obligatorio');return;}
  if(pass.length<8){showToast(' La contraseña debe tener al menos 8 caracteres');return;}
  var btn=document.getElementById('btn-register');
  if(btn){btn.textContent='  Creando...';btn.disabled=true;}
  try{
    var r=await fetch(C.API_BASE+'/vk/v1/register',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({first_name:first,last_name:last,email:email,password:pass})});
    var raw=await r.text();var d;
    try{d=JSON.parse(raw);}catch(je){console.error('[register] no JSON:',raw.substring(0,300));showToast('Error del servidor ('+r.status+'). Intenta de nuevo.');if(btn){btn.textContent='Crear mi cuenta';btn.disabled=false;}return;}
    if(r.status===409){showToast('Ya tienes cuenta. Inicia sesión.');if(btn){btn.textContent='Crear mi cuenta';btn.disabled=false;}return;}
    if(!r.ok){showToast(d.message||'Error al crear cuenta');if(btn){btn.textContent='Crear mi cuenta';btn.disabled=false;}return;}
    // Si la cuenta requiere activacion de email
    if(d.pending_verification){
      if(btn){btn.textContent='Crear mi cuenta';btn.disabled=false;}
      window._pendingEmail = d.email||email;
      showPendingActivation(window._pendingEmail);
      return;
    }
    // Cuenta social o ya activa: acceso directo
    ST.token=d.token;ST.user={id:d.user_id,name:d.display_name,email:d.email,avatar:d.avatar_url||''};
    S.set('vk_token',d.token);S.set('vk_user',ST.user);
    document.body.classList.remove('is-logged-out');document.body.classList.add('is-logged-in');
    document.getElementById('bottom-nav').style.display=(window.innerWidth>=1025?'none':'flex');
    document.getElementById('desktop-sidebar').style.display=(window.innerWidth>=1025?'flex':'none');
    document.getElementById('home-name').textContent=(d.display_name||'Estudiante')+' ';
    document.getElementById('welcome-name').textContent=d.display_name||'';
    document.getElementById('welcome-email').textContent=d.email||'';
    goto('welcome');
  }catch(e){showToast(' Error de conexión');if(btn){btn.textContent='Crear mi cuenta';btn.disabled=false;}}
}

/*  CREAR CUENTA SOCIAL  */
async function crearCuenta(){
  var first=(document.getElementById('modal-first')||{value:''}).value.trim();
  var last=(document.getElementById('modal-last')||{value:''}).value.trim();
  if(!first||!last){showToast(' Escribe tu nombre y apellido');return;}
  if(!_SD||!_SD.email){showToast(' Error: no hay datos de red social. Intenta de nuevo.');return;}
  var btn=document.getElementById('btn-modal-register');
  if(btn){btn.textContent='  Creando...';btn.disabled=true;}
  try{
    var body={first_name:first,last_name:last,email:_SD.email,social_provider:_SD.provider||'',social_access_token:_SD.access_token||'',avatar_url:_SD.avatar||''};
    var r=await fetch(C.API_BASE+'/vk/v1/register',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
    var d=await r.json();
    if(r.status===409){
      showToast(' Ya tienes cuenta. Iniciando sesión...');
      _isSocialReg=false;_SD=null;
      var m=document.getElementById('social-register-modal');if(m)m.style.display='none';
      goto('login');if(btn){btn.textContent='Crear mi cuenta  ';btn.disabled=false;}return;
    }
    if(!r.ok){showToast(' '+(d.message||'Error'));if(btn){btn.textContent='Crear mi cuenta  ';btn.disabled=false;}return;}
    _isSocialReg=false;_SD=null;SS.clear();
    var m=document.getElementById('social-register-modal');if(m)m.style.display='none';
    ST.token=d.token;
    ST.user={id:d.user_id,name:d.display_name,email:d.email,avatar:d.avatar_url||body.avatar_url||''};
    S.set('vk_token',d.token);S.set('vk_user',ST.user);
    document.body.classList.remove('is-logged-out');document.body.classList.add('is-logged-in');
    document.getElementById('bottom-nav').style.display=(window.innerWidth>=1025?'none':'flex');
    document.getElementById('desktop-sidebar').style.display=(window.innerWidth>=1025?'flex':'none');
    document.getElementById('home-name').textContent=(d.display_name||'Estudiante')+' ';
    document.getElementById('welcome-name').textContent=d.display_name||'';
    document.getElementById('welcome-email').textContent=d.email||'';
    goto('welcome');
  }catch(e){showToast(' Error de conexión');if(btn){btn.textContent='Crear mi cuenta  ';btn.disabled=false;}}
}

// Lightbox para ver imágenes a pantalla completa
function vkLightbox(src) {
  if (!src) return;
  var existing = document.getElementById('vk-lightbox');
  if (existing) existing.remove();

  var lb = document.createElement('div');
  lb.id = 'vk-lightbox';
  lb.style.cssText = 'position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,.92);'
    + 'display:flex;align-items:center;justify-content:center;'
    + 'cursor:zoom-out;animation:vkLbIn .2s ease;';

  if (!document.getElementById('vk-lb-css')) {
    var s = document.createElement('style');
    s.id = 'vk-lb-css';
    s.textContent = '@keyframes vkLbIn{from{opacity:0}to{opacity:1}}'
      + '#vk-lightbox img{max-width:96vw;max-height:92vh;object-fit:contain;'
      + 'border-radius:10px;box-shadow:0 8px 48px rgba(0,0,0,.6);'
      + 'animation:vkLbImgIn .25s cubic-bezier(.34,1.4,.64,1);user-select:none;}'
      + '@keyframes vkLbImgIn{from{transform:scale(.88)}to{transform:scale(1)}}'
      + '#vk-lb-close{position:fixed;top:1rem;right:1rem;width:36px;height:36px;'
      + 'border-radius:50%;background:rgba(255,255,255,.15);border:none;color:#fff;'
      + 'font-size:1.2rem;cursor:pointer;display:flex;align-items:center;justify-content:center;'
      + 'backdrop-filter:blur(4px);}';
    document.head.appendChild(s);
  }

  var closeBtn = document.createElement('button');
  closeBtn.id = 'vk-lb-close';
  closeBtn.innerHTML = '&#x2715;';
  closeBtn.setAttribute('aria-label', 'Cerrar');

  var imgEl = document.createElement('img');
  imgEl.src = src;
  imgEl.alt = '';

  function close() { lb.style.animation = ''; lb.style.opacity = '0'; lb.style.transition = 'opacity .15s'; setTimeout(function() { lb.remove(); }, 150); }

  lb.addEventListener('click', function(e) { if (e.target === lb) close(); });
  closeBtn.addEventListener('click', close);

  // Cerrar con ESC
  function onKey(e) { if (e.key === 'Escape') { close(); document.removeEventListener('keydown', onKey); } }
  document.addEventListener('keydown', onKey);

  // Swipe down para cerrar en móvil
  var _ty0 = 0;
  lb.addEventListener('touchstart', function(e) { _ty0 = e.touches[0].clientY; }, { passive: true });
  lb.addEventListener('touchend', function(e) { if (e.changedTouches[0].clientY - _ty0 > 60) close(); }, { passive: true });

  lb.appendChild(closeBtn);
  lb.appendChild(imgEl);
  document.body.appendChild(lb);
}

// Fallback para imágenes WordPress: intenta tamaño original, luego sin caché, luego oculta
function _attachIcon(mime){
  if(!mime)return '<i class="fas fa-file"></i>';
  if(mime.indexOf('pdf')!==-1)return '<i class="fas fa-file-pdf" style="color:#e53935"></i>';
  if(mime.indexOf('word')!==-1||mime.indexOf('document')!==-1)return '<i class="fas fa-file-word" style="color:#1565c0"></i>';
  if(mime.indexOf('excel')!==-1||mime.indexOf('spreadsheet')!==-1||mime.indexOf('csv')!==-1)return '<i class="fas fa-file-excel" style="color:#2e7d32"></i>';
  if(mime.indexOf('powerpoint')!==-1||mime.indexOf('presentation')!==-1)return '<i class="fas fa-file-powerpoint" style="color:#e65100"></i>';
  if(mime.indexOf('zip')!==-1||mime.indexOf('rar')!==-1||mime.indexOf('compress')!==-1)return '<i class="fas fa-file-zipper" style="color:#6b2447"></i>';
  if(mime.indexOf('image')!==-1)return '<i class="fas fa-file-image" style="color:#00838f"></i>';
  if(mime.indexOf('video')!==-1)return '<i class="fas fa-file-video" style="color:#6a1b9a"></i>';
  if(mime.indexOf('audio')!==-1)return '<i class="fas fa-file-audio" style="color:#ef6c00"></i>';
  if(mime.indexOf('text')!==-1)return '<i class="fas fa-file-lines"></i>';
  return '<i class="fas fa-file"></i>';
}

function _imgFallback(img){
  var src = img.src.split('?')[0]; // quitar query string existente
  // Paso 1: quitar sufijo de tamaño → nombre-300x184.jpg → nombre.jpg
  var orig = src.replace(/-\d+x\d+(\.\w+)$/, '$1');
  if (orig !== src) {
    img.onerror = function() {
      // Paso 2: reintentar el original con timestamp para saltarse la caché
      img.onerror = function() { img.style.visibility = 'hidden'; };
      img.src = orig + '?_r=' + Date.now();
    };
    img.src = orig;
  } else {
    // Ya era la URL original: reintentar una vez con timestamp
    img.onerror = function() { img.style.visibility = 'hidden'; };
    img.src = src + '?_r=' + Date.now();
  }
}

function togglePass(id,btn){
  var inp=document.getElementById(id);
  if(!inp)return;
  var show=inp.type==='password';
  inp.type=show?'text':'password';
  btn.innerHTML=show
    ?'<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>'
    :'<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
}

function showPendingActivation(email){
  showToast('Revisa tu correo '+( email||'' )+' y haz clic en el enlace de activación para entrar.');
}

/*  EMAIL LOGIN  */
async function loginEmail(){
  var user=document.getElementById('login-user').value.trim();
  var pass=document.getElementById('login-pass').value.trim();
  if(!user||!pass){showToast('Escribe tu correo y contraseña');return;}
  var btn=document.getElementById('btn-login');
  btn.textContent='Verificando...';btn.disabled=true;
  try{
    var r=await fetch(C.API_BASE+'/vk/v1/login',{
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({username:user,password:pass})
    });
    // Leer como texto primero para evitar crash si el servidor devuelve HTML
    var raw=await r.text();
    var d;
    try{d=JSON.parse(raw);}
    catch(je){
      console.error('[login] respuesta no JSON (status '+r.status+'):', raw.substring(0,300));
      showToast('Error del servidor ('+r.status+'). Intenta de nuevo.');
      btn.textContent='Entrar';btn.disabled=false;return;
    }
    if(!r.ok){
      if(d.code==='pending_activation'||(d.data&&d.data.pending_verification)){
        window._pendingEmail=(d.data&&d.data.email)||user;
        showPendingActivation(window._pendingEmail);
      } else {
        showToast(d.message||'Correo o contraseña incorrectos');
      }
      btn.textContent='Entrar';btn.disabled=false;return;
    }
    loginSuccess(d);
  }catch(e){
    console.error('[login] fetch error:',e);
    showToast('Sin conexión. Verifica tu internet e intenta de nuevo.');
    btn.textContent='Entrar';btn.disabled=false;
  }
}

function loginSuccess(d){
  // Limpiar flag de logout manual para permitir auto-login de FB en sesiones futuras
  localStorage.removeItem('_vk_fb_logout');
  ST.token=d.token;
  ST.user={id:d.user_id,name:d.display_name,email:d.email,avatar:d.avatar_url||''};
  S.set('vk_token',d.token);S.set('vk_user',ST.user);
  // Resetear botón de login por si venía de un intento anterior
  var lb=document.getElementById('btn-login');if(lb){lb.textContent='Entrar';lb.disabled=false;}
  showToast('✅ ¡Bienvenido, '+d.display_name+'!');
  setTimeout(enterApp,400);
}

function enterApp(){
  document.body.classList.remove('is-logged-out');
  document.body.classList.add('is-logged-in');
  var isDesktop=window.innerWidth>=1025;
  document.getElementById('bottom-nav').style.display=isDesktop?'none':'flex';
  document.getElementById('desktop-sidebar').style.display=isDesktop?'flex':'none';
  // Registrar player_id en el backend cuando ya tenemos token y OneSignal listo
  setTimeout(function(){ if(typeof registerPlayerWithBackend==='function') registerPlayerWithBackend(); }, 2000);
  // Mostrar prompt de notificaciones si no tiene permisos
  setTimeout(function(){ if(typeof schedulePromptIfNeeded==='function') schedulePromptIfNeeded(); }, 3500);
  document.getElementById('home-name').textContent=(ST.user&&ST.user.name?ST.user.name:'Estudiante')+' ';
  updateDesktopTopbar();
  updateMobileHeader();
  
  var navigated = false;
  if (window.VK_DEEP_LINK) {
    var dl = window.VK_DEEP_LINK;
    window.VK_DEEP_LINK = null; // consumido
    loadHomePreview();
    if (dl.course) {
      openCourseFromNotif(dl.course); navigated = true;
    } else if (dl.product) {
      openProductDetail(dl.product); navigated = true;
    } else if (dl.poll) {
      openPoll(dl.poll); navigated = true;
    } else if (dl.cert) {
      downloadCertificate(dl.cert); navigated = true;
    } else if (dl.bundle) {
      openBundle(dl.bundle); navigated = true;
    }
  }
  if (!navigated) {
    goto('home'); loadHomePreview();
  }

  if(window.m3cApplyChrome)setTimeout(window.m3cApplyChrome,0);
  // Iniciar badge de notificaciones y OneSignal
  setTimeout(function(){ initNotifBadge(); initOneSignal(); }, 800);
}

function logout(){
  if(confirm('¿Cerrar sesión?')){
    ST={user:null,token:null,courses:[],cur:null,lesson:null};
    S.del('vk_token');S.del('vk_user');S.del('vk_last');

    // Bloquear auto-login de Facebook en la sesión actual y futuras cargas
    localStorage.setItem('_vk_fb_logout','1');

    // Resetear botón de login (puede quedar en "Verificando..." si venía de login por email)
    var lb=document.getElementById('btn-login');
    if(lb){lb.textContent='Entrar';lb.disabled=false;}

    // Limpiar campos del formulario de login
    var lu=document.getElementById('login-user');if(lu)lu.value='';
    var lp=document.getElementById('login-pass');
    if(lp){lp.value='';lp.type='password';}
    // Resetear ícono del ojito si quedó en "mostrar"
    var eyeBtn=document.querySelector('#screen-login button[onclick*="login-pass"]');
    if(eyeBtn)eyeBtn.innerHTML='<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';

    // Resetear caché de acceso al chat IA
    if(window._vkc){_vkc.accessOk=null;_vkc.ready=false;_vkc.history=[];_vkc.sid=null;}
    var msgs=document.getElementById('vkc-msgs');if(msgs)msgs.innerHTML='';

    document.getElementById('bottom-nav').style.display='none';
    document.getElementById('desktop-sidebar').style.display='none';
    document.body.classList.remove('is-logged-in');
    document.body.classList.add('is-logged-out');
    goto('login');
  }
}

/*  HOME  */
async function loadHomePreview(){
  if(!ST.user||!ST.user.id)return;
  _homePreviewPromise = (async function() {
    try{
      var d=await getCached(apiURL('/vk/v1/my-courses'),120000);
      var list=(d&&Array.isArray(d.data))?d.data:[];
      if(list.length)ST.courses=list;
      else if(!Array.isArray(ST.courses))ST.courses=[];
      var prev=document.getElementById('home-courses-preview');
      if(!list.length){
        prev.innerHTML='<div style="background:var(--vk-petal);border-radius:var(--rl);padding:1.4rem 1.25rem;margin:.25rem 0;text-align:center"><div style="font-size:2.2rem;margin-bottom:.5rem"></div><p style="font-size:1rem;font-weight:700;color:var(--vk-plum);margin-bottom:.35rem;">¡Empieza a aprender!</p><p style="font-size:.86rem;color:var(--ts);margin-bottom:1rem">Explora el catálogo y encuentra tu curso</p><button onclick="goto(\'search\')" style="background:var(--grad-accent);color:white;border:none;padding:.75rem 1.5rem;border-radius:14px;font-family:\'DM Sans\',sans-serif;font-size:.9rem;font-weight:700;cursor:pointer"> Explorar cursos</button></div>';
        // Aun sin cursos, mostrar notificaciones
        loadHomeNotifications();
        return;
      }
      var last=S.get('vk_last');
      var stripCourse = last ? list.find(function(x){return x.id==last.courseId;}) : null;
      // Si no hay último curso abierto, usar el primer curso inscrito
      if(!stripCourse && list.length) stripCourse = list[0];
      if(stripCourse){
        document.getElementById('last-course-strip').style.display='flex';
        document.getElementById('last-course-name').textContent=stripCourse.post_title||'Curso';
      }
      prev.innerHTML='<p style="font-size:.85rem;font-weight:700;color:var(--ts);padding:.25rem .1rem .6rem"><i class="fas fa-book" style="color: #481531;"></i>&nbsp&nbspContinúa aprendiendo</p>'+list.slice(0,2).map(function(c){return renderCard(c,false);}).join('');
    }catch(e){}
  })();
  await _homePreviewPromise;
  // Mostrar ultimas 3 notificaciones en home
  loadHomeNotifications();
}
function continueLastCourse(){var l=S.get('vk_last');if(l&&l.courseId)openCourse(l.courseId);}

/*  CURSOS  */
async function loadCourses(){
  var el=document.getElementById('courses-list');
  el.innerHTML='<div class="spinner-wrap"><div class="spinner"></div>Cargando...</div>';
  try{
    var d=await _fetchWithRetry(apiURL('/vk/v1/my-courses')+'&_='+Date.now());
    var list=(d&&Array.isArray(d.data))?d.data:[];
    if(list.length){ST.courses=list;_cacheSet(apiURL('/vk/v1/my-courses'),d,120000);}
    else if(!Array.isArray(ST.courses))ST.courses=[];
    if(!list.length){
      el.innerHTML='<div style="text-align:center;padding:2rem"><div style="font-size:3rem;margin-bottom:1rem"></div><h3 style="font-size:1.1rem;color:var(--vk-plum);margin-bottom:.5rem;">Sin cursos inscritos</h3><button onclick="goto(\'search\')" style="background:var(--grad-accent);color:white;border:none;padding:.85rem 1.75rem;border-radius:14px;font-family:\'DM Sans\',sans-serif;font-size:.95rem;font-weight:700;cursor:pointer"> Explorar</button></div>';
      return;
    }
    el.innerHTML=list.map(function(c){return renderCard(c,false);}).join('');
  }catch(e){el.innerHTML='<div class="error-card"><h4>Error</h4><p>Intenta de nuevo.</p></div>';}
}

function renderCard(c,pub){
  var id=c.id||c.ID;
  var ttl=c.post_title||c.title||'Sin título';
  var pct=c.completed_percent||0;
  var img=c.featured_image||c.thumbnail||'';
  var level=c.level||c.course_level||'';
  var total=c.total_lessons||'';

  // Badge de tipo / nivel
  var badgeMap={'Todos los niveles':'TODOS LOS NIVELES','Básico':'CURSO BÁSICO','Intermedio':'CURSO INTERMEDIO','Avanzado':'CURSO AVANZADO','Especializado':'CURSO ESPECIALIZADO','Completo':'CURSO COMPLETO','Conferencia':'CONFERENCIA'};
  var badgeText=badgeMap[level]||(level?level.toUpperCase():'CURSO');

  // Thumb: imagen real o degradado oscuro
  var thumb=img
    ?'<div class="course-thumb"><img src="'+img+'" alt="'+ttl+'" onerror="_imgFallback(this)"><div style="position:absolute;inset:0;background:linear-gradient(160deg,rgba(58,15,40,.28),transparent 60%)"></div></div>'
    :'<div class="course-thumb-emoji"><i class="fas fa-book"></i></div>';

  if(pub){
    // Card pública (Explorar): sin barra de progreso, precio + botón "Ver  "
    var precio=c.is_free?'Gratis':(c.price||'');
    return '<div class="course-card" onclick="openPublicCourse('+id+')">'
      +thumb
      +'<div class="course-body">'
      +'<div class="course-body-top">'
      +'<span class="course-type-badge">'+badgeText+'</span>'
      +'<h3>'+ttl+'</h3>'
      +'</div>'
      +'<div class="course-body-bottom">'
      +'<div class="course-progress-area">'
      +(total?'<span style="font-size:.72rem;color:var(--ts)">'+total+' lecciones</span>':'')
      +'<div style="margin-top:.25rem"><span style="font-size:.82rem;font-weight:800;color:var(--vk-plum)">'+precio+'</span></div>'
      +'</div>'
      +'<button class="btn-see-outline" onclick="event.stopPropagation();openPublicCourse('+id+')">Ver  </button>'
      +'</div>'
      +'</div>'
      +'</div>';
  } else {
    // Card de "Mis Cursos": barra de progreso + estado + conteo + última lección
    var done=pct>=100;
    var started=pct>0;
    var completedLessons=c.completed_lessons||0;
    var lastLesson=c.last_lesson_title||'';
    var isPreview=!!(c.is_preview_enrolled);
    var statusCls=done?'mc-status mc-status-done':(isPreview?'mc-status mc-status-preview':(started?'mc-status mc-status-progress':'mc-status mc-status-new'));
    var statusTxt=done?'<i class="fas fa-check-circle"></i> Completado':(isPreview?'<i class="fas fa-eye"></i> Vista previa':(started?'<i class="fas fa-circle-play"></i> En progreso':'<i class="fas fa-circle"></i> Sin iniciar'));
    var btnLabel=done?'<i class="fa-solid fa-trophy"></i> Ver curso':(isPreview?'Ver gratis <i class="fas fa-play"></i>':(started?'Continuar <i class="fas fa-play"></i>':'Comenzar <i class="fas fa-play"></i>'));
    var countTxt=total?('<span class="mc-count">'+completedLessons+' de '+total+' lecciones</span>'):'';
    var lastTxt=lastLesson?('<div class="mc-last-lesson"><i class="fas fa-history"></i> '+lastLesson+'</div>'):'';
    return '<div class="course-card mc-card" id="mc-card-'+id+'" onclick="openCourse('+id+')">'
      +thumb
      +'<div class="course-body">'
      +'<div class="course-body-top">'
      +'<div class="mc-top-row"><span class="course-type-badge">'+badgeText+'</span><span class="'+statusCls+'">'+statusTxt+'</span></div>'
      +'<h3>'+ttl+'</h3>'
      +'</div>'
      +'<div class="mc-progress-block">'
      +'<div class="mc-progress-row">'
      +'<div class="progress-wrap"><div class="progress-fill" id="mc-fill-'+id+'" style="width:'+pct+'%"></div></div>'
      +'<span class="mc-pct" id="mc-pct-'+id+'">'+pct+'%</span>'
      +'</div>'
      +countTxt
      +lastTxt
      +'</div>'
      +'<button class="btn-see'+(done?' btn-see-done':'')+'" onclick="event.stopPropagation();openCourse('+id+')">'
      +btnLabel+'</button>'
      +'</div>'
      +'</div>';
  }
}

/*  DETALLE CURSO  */
async function openCourse(id){
  console.log('[openCourse] id='+id+' token='+(ST.token?'OK':'MISSING'));
  if (typeof closeMobileMenu === 'function') closeMobileMenu();
  if ((!ST.courses || !ST.courses.length) && _homePreviewPromise) {
    try { await _homePreviewPromise; } catch(e) {}
  }

  ST.cur={id:id};
  var _dts=document.getElementById('detail-title-short');if(_dts)_dts.textContent='Cargando...';
  document.getElementById('course-hero').innerHTML='<div class="spinner-wrap"><div class="spinner"></div></div>';
  document.getElementById('course-detail-body').innerHTML='<div class="spinner-wrap"><div class="spinner"></div>Cargando...</div>';
  goto('course-detail');
  try{
    var contents=null,_cerr={},_crs=0;
    var _delays=[0,600,1200];
    for(var _att=0;_att<_delays.length;_att++){
      if(_att>0)await new Promise(function(r){setTimeout(r,_delays[_att]);});
      var url=apiURL('/vk/v1/my-course-contents/'+id)+'&_t='+Date.now()+'&_att='+_att;
      try{
        var _cr=await fetch(url,{cache:'no-store'});
        _crs=_cr.status;
        if(_cr.ok){contents=await _cr.json();break;}
        try{_cerr=await _cr.json();}catch(x){}
        if(_crs===403||_crs===401)break;
      }catch(fe){console.warn('[openCourse] fetch err:',fe);}
    }
    if(!contents){
      var _errCode=(_cerr&&_cerr.code)||'';
      var _errMsg=(_cerr&&_cerr.message)||'';
      var _msg=_errMsg||('No se pudo cargar el curso (status '+_crs+')');
      console.error('[openCourse] FAILED status:'+_crs+' code:'+_errCode+' msg:'+_errMsg);
      var _dbgHint='';
      if(_crs===500)_dbgHint='<small style="display:block;margin-top:.4rem;opacity:.7">Código: '+_errCode+'</small>';
      document.getElementById('course-detail-body').innerHTML=
        '<div class="error-card"><h4>'+_msg+'</h4>'+_dbgHint
        +'<button onclick="openCourse('+id+')" style="margin-top:.75rem;padding:.6rem 1.2rem;'
        +'border:none;border-radius:12px;background:var(--grad-accent);color:white;'
        +'font-family:var(--ff);font-size:.88rem;font-weight:700;cursor:pointer">Reintentar</button></div>';
      return;
    }

    var course=(ST.courses||[]).find(function(c){return c.id==id;})||{};
    var title=course.post_title||'Curso';
    var enrollmentType=contents.enrollment_type||'full';
    ST.cur={
      id:id, title:title, completed:0, total:0,
      enrollmentType:enrollmentType,
      payLink:contents.payment_link||course.payment_link||'',
      ppLink:contents.paypal_link||course.paypal_link||'',
      isPaid:!!(contents.is_paid||course.is_paid),
      price:course.price||''
    };
    document.getElementById('detail-title-short').textContent=title.substring(0,22)+'…';

    var img=course.featured_image||'';
    var hero=document.getElementById('course-hero');
    if(img)hero.innerHTML='<img src="'+img+'" style="width:100%;height:100%;object-fit:cover;cursor:zoom-in" onclick="vkLightbox(this.src)" onerror="_imgFallback(this)">';
    else hero.innerHTML='<span style="font-size:4rem;position:relative;z-index:1"><i class="fas fa-book-open"></i></span>';

    var topics=(contents&&contents.topics)?contents.topics:[];
    var total=topics.reduce(function(a,t){return a+(t.contents?t.contents.length:0);},0);
    var completed=topics.reduce(function(a,t){return a+(t.contents?t.contents.filter(function(l){return l.is_completed;}).length:0);},0);
    var coursePct=parseInt(course.completed_percent||0,10);
    var localPct=total>0?Math.round((completed/total)*100):coursePct;

    var tok=ST.token||S.get('vk_token')||'';
    var serverPct=-1,serverCompleted=false,pubData={};
    await Promise.all([
      fetch(C.API_BASE+'/vk/v1/course-progress/'+id+'?vk_token='+encodeURIComponent(tok)+'&_t='+Date.now())
        .then(function(r){return r.ok?r.json():null;})
        .then(function(pg){
          if(pg&&pg.success&&typeof pg.pct==='number'){
            serverPct=pg.pct; serverCompleted=(pg.is_officially_completed===true);
            for(var ci=0;ci<(ST.courses||[]).length;ci++){
              if(ST.courses[ci].id==id){ST.courses[ci].completed_percent=serverPct;break;}
            }
            if(serverCompleted)contents.is_officially_completed=true;
          }
        }).catch(function(){}),
      fetch(C.API_BASE+'/vk/v1/public-courses/'+id+'?_t='+Date.now())
        .then(function(r){return r.ok?r.json():null;})
        .then(function(pub){if(pub&&pub.id)pubData=pub;})
        .catch(function(){})
    ]);

    // Actualizar hero con imagen de pubData si aún no se tenía
    if(!img && pubData && (pubData.featured_image||pubData.thumbnail)) {
      var pubImg=pubData.featured_image||pubData.thumbnail;
      hero.innerHTML='<img src="'+pubImg+'" style="width:100%;height:100%;object-fit:cover;cursor:zoom-in" onclick="vkLightbox(this.src)" onerror="_imgFallback(this)">';
      // Persistir en ST.courses para próximas visitas
      for(var ci=0;ci<(ST.courses||[]).length;ci++){
        if(ST.courses[ci].id==id){ST.courses[ci].featured_image=pubImg;break;}
      }
    }

    var pct=(serverPct>0)?serverPct:localPct;
    var allLessonsDone=(total>0&&completed>=total)||(serverPct>=100);
    var isCourseCompleted=serverCompleted||(contents&&contents.is_officially_completed===true);
    ST.cur.completed=completed; ST.cur.total=total;

    function lessonIcon(l){
      if(l.post_type==='tutor_quiz') return '<i class="fas fa-clipboard-list"></i>';
      var vt=l.video_type||'';
      if(vt==='youtube'||vt==='vimeo')  return '<i class="fab fa-youtube"></i>';
      if(vt==='html5'||vt==='external') return '<i class="fas fa-play-circle"></i>';
      if(vt==='embedded')               return '<i class="fas fa-film"></i>';
      return '<i class="fas fa-play-circle"></i>';
    }
    function lessonTypeLabel(l){
      if(l.post_type==='tutor_quiz') return 'Quiz';
      var vt=l.video_type||'';
      if(vt==='youtube')  return 'YouTube';
      if(vt==='vimeo')    return 'Vimeo';
      if(vt==='html5'||vt==='external'||vt==='embedded') return 'Video';
      return 'Lección';
    }

    var html='';

    // ── CABECERA STICKY DE PROGRESO ─────────────────────────────────
    var statusLbl=isCourseCompleted?'Completado':allLessonsDone?'Listo para certificar':'En progreso';
    var statusCls=isCourseCompleted?'cl-status-done':allLessonsDone?'cl-status-ready':'cl-status-progress';
    html+='<div class="cl-sticky-hdr" id="cl-sticky-hdr">'
      +'<div class="cl-hdr-row">'
      +'<h2 class="cl-hdr-title">'+title+'</h2>'
      +'<span class="cl-hdr-status '+statusCls+'">'+statusLbl+'</span>'
      +'</div>'
      +'<div class="cl-hdr-prog">'
      +'<div class="cl-prog-bar"><div class="cl-prog-fill" id="cd-progress-fill" style="width:'+pct+'%"></div></div>'
      +'<span class="cl-prog-pct" id="cd-pct-label">'+pct+'%</span>'
      +'</div>'
      +'<div class="cl-hdr-meta">'
      +'<span id="cd-pct-count">'+completed+' de '+total+' lecciones completadas</span>'
      +'</div>'
      +'</div>';

    // ── BANNER (completado / listo para certificar) ──────────────────
    if(isCourseCompleted){
      html+='<div class="cl-banner cl-banner-gold">'
        +'<div class="cl-banner-icon"><i class="fas fa-trophy"></i></div>'
        +'<div class="cl-banner-body"><strong>¡Felicidades! Curso completado</strong><span>Tu certificado está disponible para descargar</span></div>'
        +'<button class="cl-banner-btn" onclick="downloadCertificate('+id+')"><i class="fas fa-certificate"></i> Ver certificado</button>'
        +'</div>';
    }else if(allLessonsDone){
      html+='<div class="cl-banner cl-banner-rose">'
        +'<div class="cl-banner-icon"><i class="fas fa-star"></i></div>'
        +'<div class="cl-banner-body"><strong>¡Has terminado todas las lecciones!</strong><span>Genera tu certificado ahora</span></div>'
        +'<button class="cl-banner-btn" id="btn-complete-course" onclick="completeCourse('+id+')"><i class="fas fa-check-circle"></i> Completar</button>'
        +'</div>';
    }

    // ── ARCHIVOS ADICIONALES DEL CURSO ──────────────────────────────
    var courseAttachments=contents.attachments||[];
    if(courseAttachments.length){
      html+='<div class="cd-attachments">'
        +'<div class="cd-attach-title"><i class="fas fa-paperclip"></i> Archivos del curso</div>';
      courseAttachments.forEach(function(a){
        var ext=_mimeExt(a.mime);
        html+='<button class="cd-attach-item" onclick="vkOpenFile(\''+_esc(a.url)+'\',\''+_esc(a.title)+'\',\''+_esc(a.mime)+'\')" type="button">'
          +'<span class="cd-attach-icon">'+_attachIcon(a.mime)+'</span>'
          +'<span class="cd-attach-name">'+_escHtml(a.title)+'</span>'
          +(ext?'<span class="cd-attach-badge">'+ext+'</span>':'')
          +'<i class="fas fa-eye cd-attach-dl" style="color:var(--vk-rose-light,#c07090)"></i>'
          +'</button>';
      });
      html+='</div>';
    }

    // ── ACORDEÓN DE CURRICULUM ───────────────────────────────────────
    if(topics.length){
      var totalLocked=topics.reduce(function(a,t){return a+(t.contents||[]).filter(function(l){return l.is_locked;}).length;},0);
      html+='<div class="cl-curriculum">'
        +'<div class="cl-cur-hdr">'
        +'<div class="cl-cur-hdr-left"><i class="fas fa-book-open"></i><span>Contenido del curso</span></div>'
        +'<div class="cl-cur-hdr-right">'
        +'<span class="cl-cur-pill">'+topics.length+' '+(topics.length===1?'módulo':'módulos')+'</span>'
        +'<span class="cl-cur-pill">'+total+' lecciones</span>'
        +(totalLocked?'<span class="cl-cur-pill cl-pill-lock"><i class="fas fa-lock"></i> '+totalLocked+' bloqueadas</span>':'')
        +'</div>'
        +'</div>';

      topics.forEach(function(t,ti){
        var tCount=t.contents?t.contents.length:0;
        var tDone=t.contents?t.contents.filter(function(l){return l.is_completed;}).length:0;
        var tLocked=t.contents?t.contents.filter(function(l){return l.is_locked;}).length:0;
        var isOpen=(ti===0);
        var secPct=tCount>0?Math.round((tDone/tCount)*100):0;
        var secDone=tDone>=tCount&&tCount>0;
        html+='<div class="cl-sec" id="cd-sec-'+ti+'">'
          +'<div class="cl-sec-hdr'+(secDone?' cl-sec-done':'')+'" onclick="cdToggleSection('+ti+')">'
          +'<div class="cl-sec-ring" style="background:conic-gradient('+(secDone?'#16a34a':'var(--vk-rose)')+' '+secPct+'%,#f0e0ea '+secPct+'%)">'
          +'<span class="cl-sec-ring-txt">'+tDone+'/'+tCount+'</span>'
          +'</div>'
          +'<div class="cl-sec-info">'
          +'<span class="cl-sec-title">'+(t.post_title||'Módulo '+(ti+1))+'</span>'
          +'<span class="cl-sec-sub">'+(secDone?'<i class="fas fa-check-circle" style="color:#16a34a"></i> Completada':tDone+' de '+tCount+' completadas')+(tLocked?' · <i class="fas fa-lock" style="color:#bbb;font-size:.62rem"></i> '+tLocked+' bloqueadas':'')+'</span>'
          +'</div>'
          +'<i class="fas fa-chevron-down cl-sec-chev'+(isOpen?' rotated':'')+'" id="cd-sec-chev-'+ti+'"></i>'
          +'</div>'
          +'<div class="cl-sec-body'+(isOpen?' open':'')+'" id="cd-lessons-'+ti+'">';

        (t.contents||[]).forEach(function(l,li){
          var lid=l.id,ltit=l.post_title||'Lección '+(li+1);
          var done=l.is_completed,locked=l.is_locked,preview=!!l.is_preview;
          var safe=ltit.replace(/\\/g,'\\\\').replace(/'/g,"\\'").replace(/"/g,'&quot;');
          var lockedFn=l.locked_reason==='payment_required'?'showPaymentModal('+id+')':'showLockedLessonInfo()';
          var clickFn=locked?lockedFn:'openLesson('+lid+',\''+safe+'\','+id+',\''+l.post_type+'\')';
          var isQuiz=l.post_type==='tutor_quiz';

          // Ícono de tipo de contenido
          var typeIcon;
          if(isQuiz){
            typeIcon='<i class="fas fa-clipboard-question"></i>';
          }else{
            var vt=l.video_type||'';
            if(vt==='youtube'||vt==='vimeo') typeIcon='<i class="fab fa-youtube"></i>';
            else if(vt==='html5'||vt==='external') typeIcon='<i class="fas fa-circle-play"></i>';
            else if(vt==='embedded') typeIcon='<i class="fas fa-film"></i>';
            else typeIcon='<i class="fas fa-circle-play"></i>';
          }

          // Estado visual del ícono circular — ln-done en completadas para que _calcProgressFromDOM las cuente
          var iconCls=done?'cl-ls-ic cl-ic-done ln-done':locked?'cl-ls-ic cl-ic-locked':'cl-ls-ic cl-ic-pending';
          var iconContent=done?'<i class="fas fa-check"></i>':locked?'<i class="fas fa-lock"></i>':typeIcon;

          // Tipo + duración
          var typeLabel=isQuiz?'Examen':(l.video_type?'Video':'Lección');
          var metaStr=typeLabel+(l.duration?' · '+l.duration:'');

          // Badge derecho de estado — id="cd-lb-{lid}" para que _markLessonRowDone pueda actualizarlo
          var stBadge='';
          if(done)stBadge='<span id="cd-lb-'+lid+'" class="cl-st cl-st-done"><i class="fas fa-check"></i> Vista</span>';
          else if(locked&&l.locked_reason==='payment_required')stBadge='<span id="cd-lb-'+lid+'" class="cl-st cl-st-pay"><i class="fas fa-crown"></i> Premium</span>';
          else if(locked)stBadge='<span id="cd-lb-'+lid+'" class="cl-st cl-st-lock"><i class="fas fa-lock"></i></span>';
          else if(preview)stBadge='<span id="cd-lb-'+lid+'" class="cl-st cl-st-free"><i class="fas fa-play"></i> Gratis</span>';
          else stBadge='<span id="cd-lb-'+lid+'" class="cl-st cl-st-avail"><i class="fas fa-circle-play"></i></span>';

          html+='<div class="cl-lesson'+(done?' cl-lesson-done':locked?' cl-lesson-locked':'')+'" id="cd-lr-'+lid+'" onclick="'+clickFn+'">'
            +'<div class="'+iconCls+'" id="cd-ln-'+lid+'">'+iconContent+'</div>'
            +'<div class="cl-ls-body">'
            +'<span class="cl-ls-title">'+ltit+'</span>'
            +'<span class="cl-ls-meta">'+metaStr+'</span>'
            +'</div>'
            +stBadge
            +'</div>';
        });

        html+='</div></div>';
      });
      html+='</div>';
    }else{
      html+='<div class="cl-empty">'
        +'<i class="fas fa-hourglass-half"></i>'
        +'<p>El contenido estará disponible pronto</p>'
        +'<button onclick="openCourse('+id+')" class="cl-refresh-btn"><i class="fas fa-rotate-right"></i> Actualizar</button>'
        +'</div>';
    }

    document.getElementById('course-detail-body').innerHTML=html;

    var descEl=document.getElementById('cd-desc');
    var toggleBtn=document.getElementById('cd-desc-toggle');
    if(descEl&&toggleBtn&&descEl.scrollHeight<=180){toggleBtn.style.display='none';}

  }catch(e){
    console.error('[openCourse]',e);
    var isNet=!navigator.onLine||e.message==='Failed to fetch'||String(e).includes('fetch');
    var msg=isNet
      ?'<h4>Sin conexión a internet</h4><p>Verifica tu red e intenta de nuevo.</p><button onclick="location.reload()" style="margin-top:.75rem;padding:.55rem 1.25rem;background:#1b4332;color:#fff;border:none;border-radius:10px;font-weight:700;cursor:pointer">Reintentar</button>'
      :'<h4>No se pudo cargar el curso</h4><p>Recarga la app para continuar.</p><button onclick="location.reload()" style="margin-top:.75rem;padding:.55rem 1.25rem;background:#1b4332;color:#fff;border:none;border-radius:10px;font-weight:700;cursor:pointer">Recargar app</button>';
    document.getElementById('course-detail-body').innerHTML='<div class="error-card" style="text-align:center;padding:2rem">'+msg+'</div>';
  }
}

function cdToggleDesc(){
  var wrap=document.getElementById('cd-desc-wrap');
  var btn=document.getElementById('cd-desc-toggle');
  if(!wrap)return;
  var expanded=wrap.classList.toggle('cd-desc-expanded');
  if(btn)btn.innerHTML='<i class="fas fa-chevron-'+(expanded?'up':'down')+'"></i> '+(expanded?'Ver menos':'Ver más');
}

function cdToggleSection(ti){
  var body=document.getElementById('cd-lessons-'+ti);
  var chev=document.getElementById('cd-sec-chev-'+ti);
  if(!body)return;
  var opening=!body.classList.contains('open');
  body.classList.toggle('open',opening);
  if(chev){chev.className='fas fa-chevron-down cl-sec-chev'+(opening?' rotated':'');}
}

/* Completar el curso manualmente tras terminar las lecciones */
async function completeCourse(courseId) {
  var btn = document.getElementById('btn-complete-course');
  if (btn) { btn.textContent = '  Completando...'; btn.disabled = true; }
  showToast('  Sincronizando con Tutor LMS...');
  
  var tok = ST.token || S.get('vk_token') || '';
  try {
    var r = await fetch(C.API_BASE + '/vk/v1/complete-course', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ vk_token: tok, course_id: courseId })
    });
    var d = await r.json();
    if (d.success) {
      showToast(' ¡Felicidades! Has completado el curso.');
      // Pasar el cert_hash directamente a downloadCertificate para evitar búsquedas adicionales
      var certHash = d.cert_hash || '';
      setTimeout(function() {
        downloadCertificate(courseId, certHash);
      }, 1500);
    } else {
      showToast(' Error: ' + (d.message || 'No se pudo completar'));
      if (btn) { btn.textContent = ' Completar curso'; btn.disabled = false; }
    }
  } catch (e) {
    showToast(' Error de conexión al completar curso');
    if (btn) { btn.textContent = ' Completar curso'; btn.disabled = false; }
  }
}

/*  LECCIÓN  */
/* Preinscripción gratuita en curso de pago — acceso a lecciones preview */
async function previewEnrollCourse(id){
  if(!ST.user||!ST.user.id){showToast('Inicia sesión primero');goto('login');return;}
  var btn=document.getElementById('btn-preview-enroll');
  if(btn){btn.textContent='Procesando...';btn.disabled=true;}
  var tok=ST.token||S.get('vk_token')||'';
  try{
    var r=await fetch(C.API_BASE+'/vk/v1/preview-enroll?vk_token='+encodeURIComponent(tok),{
      method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({course_id:id})
    });
    var d=await r.json();
    if(!r.ok){showToast(d.message||'Error al preinscribirse');if(btn){btn.textContent='Probar gratis';btn.disabled=false;}return;}
    showToast('<i class="fas fa-check"></i> ¡Acceso gratuito activado!');
    // Agregar el curso al estado local como preview-enrolled
    if(!ST.courses)ST.courses=[];
    if(!ST.courses.find(function(x){return x.id==id;})){
      ST.courses.push({
        id:id, post_title:d.course_title||'Curso', completed_percent:0,
        total_lessons:0, completed_lessons:0, enroll_status:'preview',
        is_preview_enrolled:true, featured_image:''
      });
    }
    openCourse(id);
  }catch(e){showToast('Error de conexión');if(btn){btn.textContent='Probar gratis';btn.disabled=false;}}
}

/* Modal de pago para lecciones bloqueadas por falta de pago */
function showPaymentModal(courseId){
  var course=(ST.courses||[]).find(function(c){return c.id==courseId;})||{};
  var cur=ST.cur||{};
  var payLink=cur.payLink||course.payment_link||'';
  var ppLink=cur.ppLink||course.paypal_link||'';
  var price=cur.price||'';
  var title=cur.title||course.post_title||'este curso';
  var waMsg=buildWaEnrollMsg(title,price);
  var overlay=document.createElement('div');
  overlay.id='payment-modal-overlay';
  overlay.style.cssText='position:fixed;inset:0;background:rgba(26,10,21,.75);z-index:9999;display:flex;align-items:flex-end;justify-content:center;animation:fadeIn .2s ease';
  overlay.innerHTML='<div style="background:#fff;border-radius:20px 20px 0 0;padding:1.5rem 1.4rem 2.2rem;max-width:480px;width:100%;box-shadow:0 -4px 32px rgba(0,0,0,.2)">'
    +'<div style="width:40px;height:4px;background:#e0d0da;border-radius:2px;margin:0 auto .9rem"></div>'
    +'<div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1rem">'
    +'<div style="width:46px;height:46px;border-radius:50%;background:linear-gradient(135deg,#fff0f5,#ffe4f0);display:flex;align-items:center;justify-content:center;flex-shrink:0">'
    +'<i class="fas fa-crown" style="color:var(--vk-rose);font-size:1.25rem"></i></div>'
    +'<div><strong style="font-size:.98rem;color:var(--vk-plum)">Contenido Premium</strong>'
    +'<p style="font-size:.82rem;color:#888;margin:.15rem 0 0">Esta lección requiere el curso completo.</p></div>'
    +'</div>'
    +(price?'<p style="font-size:1.1rem;font-weight:800;color:var(--vk-rose);text-align:center;margin-bottom:1rem">'+price+'</p>':'')
    +(payLink?'<a class="pm-btn pm-btn-mp" href="'+payLink+'" target="_blank"><i class="fas fa-credit-card"></i> Pagar con Mercado Pago</a>':'')
    +(ppLink?'<a class="pm-btn pm-btn-pp" href="'+ppLink+'" target="_blank"><i class="fab fa-paypal"></i> Pagar con PayPal</a>':'')
    +'<a class="pm-btn pm-btn-wa" href="'+buildWaLink(waMsg)+'" target="_blank"><i class="fab fa-whatsapp"></i> Consultar por WhatsApp</a>'
    +'<button onclick="document.getElementById(\'payment-modal-overlay\').remove()" '
    +'style="width:100%;padding:.75rem;border:1.5px solid #e5e7eb;border-radius:12px;background:#fff;color:#888;font-size:.88rem;font-weight:600;font-family:var(--ff);cursor:pointer;margin-top:.6rem">Cerrar</button>'
    +'</div>';
  overlay.addEventListener('click',function(e){if(e.target===overlay)overlay.remove();});
  document.body.appendChild(overlay);
}

/* Informa al usuario que una lección está bloqueada por acceso secuencial */
function showLockedLessonInfo(){
  var overlay=document.createElement('div');
  overlay.id='locked-lesson-overlay';
  overlay.style.cssText='position:fixed;inset:0;background:rgba(26,10,21,.72);z-index:9999;display:flex;align-items:flex-end;justify-content:center;animation:fadeIn .2s ease';
  overlay.innerHTML='<div style="background:#fff;border-radius:20px 20px 0 0;padding:1.5rem 1.4rem 2rem;max-width:480px;width:100%;box-shadow:0 -4px 32px rgba(0,0,0,.18)">'
    +'<div style="width:40px;height:4px;background:#e0d0da;border-radius:2px;margin:0 auto .85rem"></div>'
    +'<div style="display:flex;align-items:center;gap:.75rem;margin-bottom:.9rem">'
    +'<div style="width:44px;height:44px;border-radius:50%;background:#fff0f5;display:flex;align-items:center;justify-content:center;flex-shrink:0">'
    +'<i class="fas fa-lock" style="color:var(--vk-rose);font-size:1.2rem"></i></div>'
    +'<div><strong style="font-size:.98rem;color:var(--vk-plum)">Lección bloqueada</strong>'
    +'<p style="font-size:.82rem;color:#888;margin:.15rem 0 0">Completa las lecciones anteriores para desbloquear este contenido.</p></div>'
    +'</div>'
    +'<button onclick="document.getElementById(\'locked-lesson-overlay\').remove()" '
    +'style="width:100%;padding:.82rem;border:none;border-radius:12px;background:var(--grad-accent);color:#fff;font-size:.93rem;font-weight:700;font-family:var(--ff);cursor:pointer">Entendido</button>'
    +'</div>';
  overlay.addEventListener('click',function(e){if(e.target===overlay)overlay.remove();});
  document.body.appendChild(overlay);
}

/* Regresa desde el reproductor de lección al detalle del curso de forma segura */
function backFromLesson(){
  if(_autoMarkTimer){clearTimeout(_autoMarkTimer);_autoMarkTimer=null;}
  stopActiveVideo();
  var cid=(ST.cur&&ST.cur.id)||(ST.lesson&&ST.lesson.courseId);
  // Verificar que course-detail-body tenga contenido válido (no error, no spinner)
  var body=document.getElementById('course-detail-body');
  var hasValid=body&&!body.querySelector('.error-card')&&body.querySelector('.cd-header');
  if(hasValid){
    goto('course-detail');
  }else if(cid){
    openCourse(cid);
  }else{
    goto('courses');
  }
}

function stopActiveVideo(){
  // Detener iframes (YouTube, Vimeo, embeds)
  var vc=document.getElementById('video-container');
  if(!vc)return;
  var iframes=vc.querySelectorAll('iframe');
  iframes.forEach(function(f){
    // Quitar src para forzar detención
    var s=f.src;
    f.src='';
    f.src=s; // reset para que pueda volver a cargar si regresa
    // Más seguro: limpiar todo
    f.src='';
  });
  // Detener videos HTML5
  var vids=vc.querySelectorAll('video');
  vids.forEach(function(v){try{v.pause();v.currentTime=0;}catch(e){}});
  // Limpiar el contenedor por completo para evitar autoplay residual
  vc.innerHTML='';
}

var _autoMarkTimer=null;
function openLesson(lid,title,cid,type){
  if(type==='tutor_quiz'){openQuiz(lid,title,cid);return;}
  ST.lesson={lessonId:lid,title:title,courseId:cid};
  S.set('vk_last',{courseId:cid,lessonId:lid});
  document.getElementById('lesson-course-label').textContent=(ST.cur&&ST.cur.title)||'';
  document.getElementById('lesson-title').textContent=title;
  document.getElementById('lesson-desc').textContent='';
  document.getElementById('lesson-attachments').innerHTML='';
  var vw=document.getElementById('vk-video-wrapper');
  if(vw){vw.classList.remove('vk-no-media');}
  document.getElementById('video-container').innerHTML='<div style="aspect-ratio:16/9;display:flex;align-items:center;justify-content:center"><div style="width:52px;height:52px;border:3px solid #2a0d1e;border-top-color:var(--vk-rose);border-radius:50%;animation:spin .8s linear infinite"></div></div>';
  var btn=document.getElementById('btn-lesson-done');
  if(btn){btn.textContent='👁 Marcar como vista';btn.disabled=false;btn.style.opacity='1';btn.style.background='';}
  if(_autoMarkTimer){clearTimeout(_autoMarkTimer);_autoMarkTimer=null;}
  goto('lesson');
  loadLessonVideo(lid);
}

async function loadLessonVideo(lid){
  try{
    var d=await getJSON(apiURL('/vk/v1/my-lesson/'+lid));
    var atts=d.attachments||[];

    // ── Descripción ────────────────────────────────────────
    if(d.content){
      var descEl=document.getElementById('lesson-desc');
      var tmp=document.createElement('div');
      tmp.innerHTML=d.content;
      var selectors=[
        '.tutor-lesson-wrapper',
        '.tutor-fs-6.tutor-color-secondary',
        '.tutor-tab-item.is-active .tutor-col-xl-8',
        '#tutor-course-spotlight-overview .tutor-col-xl-8',
        '#tutor-course-spotlight-overview',
        '.tutor-tab-item.is-active'
      ];
      var wrapper=null;
      for(var si=0;si<selectors.length;si++){
        wrapper=tmp.querySelector(selectors[si]);
        if(wrapper&&wrapper.textContent.trim().length>30){break;}
        wrapper=null;
      }
      if(wrapper){
        ['form','input','textarea','button','.tutor-lesson-note-form-wrapper'].forEach(function(sel){
          wrapper.querySelectorAll(sel).forEach(function(el){el.remove();});
        });
        descEl.innerHTML=wrapper.innerHTML;
      } else {
        ['form','nav','input','textarea','button',
         '.tutor-course-spotlight-nav','.tutor-lesson-note-form-wrapper',
         '#tutor-take-lesson-note-btn','#tutor-lesson-nav-take-note-btn',
         '.tutor-course-spotlight-notes'].forEach(function(sel){
          tmp.querySelectorAll(sel).forEach(function(el){el.remove();});
        });
        descEl.innerHTML=tmp.innerHTML;
      }
    }

    // ── Archivos adjuntos en #lesson-attachments ───────────
    var attEl=document.getElementById('lesson-attachments');
    if(attEl){
      if(atts.length){
        var aHtml='<div class="cd-attachments" style="margin-top:1.25rem">'
          +'<div class="cd-attach-title"><i class="fas fa-paperclip"></i> Archivos de esta lección</div>';
        atts.forEach(function(a){
          var ext=_mimeExt(a.mime);
          aHtml+='<button class="cd-attach-item" onclick="vkOpenFile(\''+_esc(a.url)+'\',\''+_esc(a.title)+'\',\''+_esc(a.mime)+'\')" type="button">'
            +'<span class="cd-attach-icon">'+_attachIcon(a.mime)+'</span>'
            +'<span class="cd-attach-name">'+_escHtml(a.title)+'</span>'
            +(ext?'<span class="cd-attach-badge">'+ext+'</span>':'')
            +'<i class="fas fa-eye cd-attach-dl" style="color:var(--vk-rose-light,#c07090)"></i>'
            +'</button>';
        });
        aHtml+='</div>';
        attEl.innerHTML=aHtml;
      } else {
        attEl.innerHTML='';
      }
    }

    // ── Botón completado ───────────────────────────────────
    if(d.is_completed){
      var btn=document.getElementById('btn-lesson-done');
      if(btn){btn.innerHTML='<i class="fa-solid fa-check"></i> Vista';btn.disabled=true;btn.style.opacity='.6';btn.style.background='linear-gradient(135deg,#2e7d32,#1b5e20)';}
    }

    // ── Video / media ──────────────────────────────────────
    renderVideo(d.video_url||'',d.video_type||'',d.embed_html||'',atts);

  }catch(e){
    console.error('[VK Lesson]',e);
    var vw=document.getElementById('vk-video-wrapper');
    if(vw) vw.classList.add('vk-no-media');
  }
}

function renderVideo(url,type,embedHtml,attachments){
  var c=document.getElementById('video-container');
  var vw=document.getElementById('vk-video-wrapper');
  attachments=attachments||[];

  function _showWrapper(){ if(vw) vw.classList.remove('vk-no-media'); }
  function _hideWrapper(){ if(vw) vw.classList.add('vk-no-media'); c.innerHTML=''; }

  if(embedHtml){
    _showWrapper();
    c.innerHTML='<div style="position:relative;width:100%;aspect-ratio:16/9">'+embedHtml.replace(/width="[^"]*"/,'width="100%"').replace(/height="[^"]*"/,'height="100%"').replace(/<iframe/,'<iframe style="position:absolute;inset:0;width:100%;height:100%;border:none"')+'</div>';
    return;
  }
  if(url){
    _showWrapper();
    var yt=url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/);
    if(yt||type==='youtube'){c.innerHTML='<div style="position:relative;width:100%;aspect-ratio:16/9"><iframe src="https://www.youtube.com/embed/'+(yt?yt[1]:url)+'?autoplay=1&rel=0" style="position:absolute;inset:0;width:100%;height:100%;border:none" allow="accelerometer;autoplay;clipboard-write;encrypted-media;gyroscope;picture-in-picture" allowfullscreen></iframe></div>';return;}
    var vm=url.match(/vimeo\.com\/(\d+)/);
    if(vm||type==='vimeo'){c.innerHTML='<div style="position:relative;width:100%;aspect-ratio:16/9"><iframe src="https://player.vimeo.com/video/'+(vm?vm[1]:url)+'?autoplay=1" style="position:absolute;inset:0;width:100%;height:100%;border:none" allow="autoplay;fullscreen" allowfullscreen></iframe></div>';return;}
    if(url.match(/\.(mp4|webm|ogg)/i)||type==='html5'){c.innerHTML='<video controls autoplay playsinline style="width:100%;aspect-ratio:16/9;background:#000" src="'+url+'"></video>';return;}
    c.innerHTML='<div style="position:relative;width:100%;aspect-ratio:16/9"><iframe src="'+url+'" style="position:absolute;inset:0;width:100%;height:100%;border:none" allowfullscreen></iframe></div>';
    return;
  }

  // Sin video y sin attachments → ocultar wrapper
  if(!attachments.length){ _hideWrapper(); return; }

  // Sin video pero hay attachments → ocultar también el wrapper
  // Los archivos ya se muestran en #lesson-attachments debajo de la descripción
  _hideWrapper();
}

/* ═══════════════════════════════════════════════════════════
   PANTALLA COMPLETA — Compatible Android + iOS (PWA/Safari)
═══════════════════════════════════════════════════════════ */
(function(){
  var _isIOS = /iphone|ipad|ipod/i.test(navigator.userAgent);
  var _iosFs  = false;

  function getFsWrapper(){ return document.getElementById('vk-video-wrapper'); }

  function _updateFsBtn(show){
    var btn = document.getElementById('vk-fs-btn');
    if(!btn) return;
    btn.classList[show ? 'add' : 'remove']('vk-fs-visible');
  }

  function _setFsIcon(isFs){
    var ico = document.getElementById('vk-fs-icon');
    if(!ico) return;
    ico.className = isFs ? 'fas fa-compress' : 'fas fa-expand';
  }

  function _lockLandscape(){
    try{
      if(screen.orientation && screen.orientation.lock){
        screen.orientation.lock('landscape').catch(function(){});
      } else if(screen.lockOrientation){
        screen.lockOrientation('landscape');
      }
    }catch(e){}
  }

  function _unlockOrientation(){
    try{
      if(screen.orientation && screen.orientation.unlock) screen.orientation.unlock();
      else if(screen.unlockOrientation) screen.unlockOrientation();
    }catch(e){}
  }

  function _isFullscreen(){
    return !!(document.fullscreenElement || document.webkitFullscreenElement ||
              document.mozFullScreenElement || document.msFullscreenElement || _iosFs);
  }

  /* Fallback iOS: pantalla completa via CSS position:fixed */
  function _enterIosFs(){
    var w = getFsWrapper(); if(!w) return;
    _iosFs = true;
    w.classList.add('vk-ios-fs');
    var topbar = document.querySelector('#screen-lesson .video-topbar');
    if(topbar) topbar.style.display = 'none';
    var bottomNav = document.getElementById('bottom-nav');
    if(bottomNav) bottomNav.style.display = 'none';
    var bar = document.getElementById('vk-ios-fs-bar');
    if(bar) bar.classList.add('visible');
    _setFsIcon(true);
    _lockLandscape();
    document.body.style.overflow = 'hidden';
  }

  function _exitIosFs(){
    var w = getFsWrapper(); if(!w) return;
    _iosFs = false;
    w.classList.remove('vk-ios-fs');
    var topbar = document.querySelector('#screen-lesson .video-topbar');
    if(topbar) topbar.style.display = '';
    var bottomNav = document.getElementById('bottom-nav');
    if(bottomNav) bottomNav.style.display = '';
    var bar = document.getElementById('vk-ios-fs-bar');
    if(bar) bar.classList.remove('visible');
    _setFsIcon(false);
    _unlockOrientation();
    document.body.style.overflow = '';
  }

  window.vkExitIosFs = _exitIosFs;

  function _enterFs(){
    var w = getFsWrapper(); if(!w) return;
    var req = w.requestFullscreen || w.webkitRequestFullscreen ||
              w.mozRequestFullScreen || w.msRequestFullscreen;
    if(req){
      req.call(w).then(function(){
        _lockLandscape();
        _setFsIcon(true);
      }).catch(function(){ if(_isIOS) _enterIosFs(); });
    } else {
      _enterIosFs();
    }
  }

  function _exitFs(){
    if(_iosFs){ _exitIosFs(); return; }
    var exit = document.exitFullscreen || document.webkitExitFullscreen ||
               document.mozCancelFullScreen || document.msExitFullscreen;
    if(exit) exit.call(document).then(function(){ _unlockOrientation(); _setFsIcon(false); }).catch(function(){});
  }

  window.vkToggleFullscreen = function(){
    _isFullscreen() ? _exitFs() : _enterFs();
  };

  function _onFsChange(){
    var isFs = !!(document.fullscreenElement || document.webkitFullscreenElement ||
                  document.mozFullScreenElement || document.msFullscreenElement);
    _setFsIcon(isFs);
    if(!isFs){ _unlockOrientation(); }
  }

  ['fullscreenchange','webkitfullscreenchange','mozfullscreenchange','MSFullscreenChange']
    .forEach(function(ev){ document.addEventListener(ev, _onFsChange); });

  /* MutationObserver: muestra el boton cuando hay iframe o video */
  function _observeVideoContainer(){
    var vc = document.getElementById('video-container');
    if(!vc) return;
    var obs = new MutationObserver(function(){
      _updateFsBtn(!!vc.querySelector('iframe,video'));
    });
    obs.observe(vc, {childList:true});
  }

  if(document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', _observeVideoContainer);
  } else {
    _observeVideoContainer();
  }
})();


async function markLessonDone(){
  if(!ST.lesson)return;
  var btn=document.getElementById('btn-lesson-done');
  if(btn){btn.textContent='Guardando...';btn.disabled=true;}
  var tok=ST.token||S.get('vk_token')||'';
  var cId=ST.lesson.courseId;
  var lId=ST.lesson.lessonId;

  try{
    var r=await fetch(C.API_BASE+'/vk/v1/my-lesson-complete',{
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({vk_token:tok,lesson_id:lId,course_id:cId})
    });
    var d=await r.json();
    showToast('<i class="fa-solid fa-check"></i> ¡Lección completada!');
    if(btn){btn.innerHTML='<i class="fa-solid fa-check"></i> Vista';btn.disabled=true;btn.style.opacity='.6';btn.style.background='linear-gradient(135deg,#2e7d32,#1b5e20)';}

    // Marcar la fila de lección como completada en el DOM del curso
    _markLessonRowDone(lId);

    // 1. Progreso desde el DOM — instantáneo, no depende de API
    var domProg = _calcProgressFromDOM();
    if(domProg && domProg.total > 0){
      console.log('[prog] DOM pct='+domProg.pct+' ('+domProg.completed+'/'+domProg.total+')');
      _updateCourseProgressDOM(domProg.pct, domProg.completed, domProg.total);
      for(var ci=0;ci<(ST.courses||[]).length;ci++){
        if(ST.courses[ci].id==cId){ST.courses[ci].completed_percent=domProg.pct;break;}
      }
    }

    // 2. Verificar con el API si hay datos del servidor (para detectar course_completed)
    var immPct = -1;
    console.log('[prog] my-lesson-complete d=', d);
    if(d&&typeof d.completed_lessons==='number'&&d.total_lessons>0){
      immPct=Math.round((d.completed_lessons/d.total_lessons)*100);
      console.log('[prog] servidor pct='+immPct+' ('+d.completed_lessons+'/'+d.total_lessons+')');
      if(immPct > (domProg ? domProg.pct : 0)){
        _updateCourseProgressDOM(immPct,d.completed_lessons,d.total_lessons);
        for(var ci=0;ci<(ST.courses||[]).length;ci++){
          if(ST.courses[ci].id==cId){ST.courses[ci].completed_percent=immPct;break;}
        }
      }
    }

    // Si se completaron TODAS las lecciones mostrar felicitación pero sin cerrar el video
    if(d && d.course_completed === true && cId){
      setTimeout(function(){ completeCourse(cId); }, 900);
    }

  }catch(e){
    showToast('<i class="fa-solid fa-check"></i> Progreso guardado');
    if(btn){btn.innerHTML='<i class="fa-solid fa-check"></i> Vista';btn.disabled=true;}
    _markLessonRowDone(lId);
  }
}

/* Marca visualmente la fila de lección como completada en course-detail-body */
function _markLessonRowDone(lid){
  var numEl=document.getElementById('cd-ln-'+lid);
  var badgeEl=document.getElementById('cd-lb-'+lid);
  var rowEl=document.getElementById('cd-lr-'+lid);
  if(numEl){numEl.className='cl-ls-ic cl-ic-done ln-done';numEl.innerHTML='<i class="fas fa-check"></i>';}
  if(badgeEl){badgeEl.className='cl-st cl-st-done';badgeEl.innerHTML='<i class="fas fa-check"></i> Vista';}
  if(rowEl){rowEl.classList.remove('cl-lesson-locked');rowEl.classList.add('cl-lesson-done');}
}

/* Calcula el progreso leyendo el DOM — 100% confiable, no depende de API */
function _calcProgressFromDOM(){
  var allRows  = document.querySelectorAll('[id^="cd-lr-"]');
  var doneIcos = document.querySelectorAll('[id^="cd-ln-"].ln-done');
  var total    = allRows.length;
  var done     = doneIcos.length;
  if(total===0) return null;
  return {total:total, completed:done, pct:Math.round(done/total*100)};
}

/* Actualiza barra y contadores de progreso en course-detail-body sin recargar */
function _updateCourseProgressDOM(pct,completed,total){
  var lbl=document.getElementById('cd-pct-label');
  var cnt=document.getElementById('cd-pct-count');
  var fill=document.getElementById('cd-progress-fill');
  if(lbl)lbl.textContent=pct+'%';
  if(cnt&&completed!=null&&total!=null)cnt.textContent=completed+' de '+total+' lecciones completadas';
  if(fill)fill.style.width=pct+'%';
  if(ST.cur){ST.cur.completed=completed||ST.cur.completed;ST.cur.total=total||ST.cur.total;}
  // Actualizar el card en la lista "Mis Cursos" si está en el DOM
  var cid=ST.cur&&ST.cur.id;
  if(cid){
    var mcFill=document.getElementById('mc-fill-'+cid);
    var mcPct=document.getElementById('mc-pct-'+cid);
    if(mcFill)mcFill.style.width=pct+'%';
    if(mcPct)mcPct.textContent=pct+'%';
    // Actualizar conteo en ST.courses para que renderCard use el valor actualizado
    for(var ci=0;ci<(ST.courses||[]).length;ci++){
      if(ST.courses[ci].id==cid){
        ST.courses[ci].completed_percent=pct;
        if(completed!=null)ST.courses[ci].completed_lessons=completed;
        break;
      }
    }
  }
  // A 100%: inyectar banner de certificado en course-detail si no existe
  if(pct>=100&&cid){
    var body=document.getElementById('course-detail-body');
    if(body&&!document.getElementById('cd-cert-banner')){
      var banner=document.createElement('div');
      banner.id='cd-cert-banner';
      banner.className='cl-banner cl-banner-rose';
      banner.innerHTML='<div class="cl-banner-icon"><i class="fas fa-star"></i></div>'
        +'<div class="cl-banner-body"><strong>¡Has terminado todas las lecciones!</strong>'
        +'<span>Genera tu certificado ahora</span></div>'
        +'<button class="cl-banner-btn" onclick="completeCourse('+cid+')">'
        +'<i class="fas fa-check-circle"></i> Obtener Certificado</button>';
      var curriculum=body.querySelector('.cl-curriculum')||body.querySelector('.cl-pay-cta');
      if(curriculum)body.insertBefore(banner,curriculum);
      else body.appendChild(banner);
    }
  }
}

/*  CERTIFICADO  */
async function downloadCertificate(courseId, knownCertHash) {
  var course = (ST.courses || []).find(function(c){ return c.id == courseId; }) || {};
  var title  = course.post_title || 'Certificado';
  var tok    = ST.token || S.get('vk_token') || '';

  // Primero verificar si ya hay imagen guardada
  try {
    var d = await getJSON(apiURL('/vk/v1/my-certificate/' + courseId));
    if (d && d.cert_img) {
      _certFrom = 'course-detail';
      showCertViewer('', d.cert_img, title, courseId, d.cert_hash || knownCertHash || '');
      return;
    }
    // Tenemos hash (o nos lo pasaron)   ir directo a la pantalla y generar in-app
    var certHash = (d && d.cert_hash) ? d.cert_hash : (knownCertHash || '');
    if (certHash) {
      _certFrom = 'course-detail';
      showCertViewer('', '', title, courseId, certHash);
      return;
    }
  } catch(e) {}

  // Sin hash ni imagen guardada — ir al visor para generar in-app
  _certFrom = 'course-detail';
  showCertViewer('', '', title, courseId, knownCertHash || '');
}


/*  QUIZ  */
async function openQuiz(qid,title,cid){
  _quiz={id:qid,title:title,courseId:cid};_quizAnswers={};
  if(cid&&(!ST.cur||!ST.cur.id))ST.cur={id:cid,title:''};
  var _qTg=document.getElementById('quiz-title');if(_qTg)_qTg.textContent=title;
  document.getElementById('quiz-body').innerHTML='<div class="spinner-wrap"><div class="spinner"></div>Cargando...</div>';
  goto('quiz');
  try{
    var d=await getJSON(apiURL('/vk/v1/my-quiz/'+qid));
    if(!d.questions||!d.questions.length){document.getElementById('quiz-body').innerHTML='<div class="error-card"><h4>Sin preguntas</h4></div>';return;}
    renderQuiz(d);
  }catch(e){document.getElementById('quiz-body').innerHTML='<div class="error-card"><h4>Error</h4></div>';}
}
function renderQuiz(data){
  var html='<div style="background:var(--vk-petal);border-radius:var(--r);padding:1rem 1.1rem;margin-bottom:1.25rem"><p style="font-size:.93rem;font-weight:700;color:var(--vk-plum);"> '+data.title+'</p><p style="font-size:.8rem;color:var(--ts);margin-top:.25rem">'+data.total+' preguntas  Aprobación: '+(data.options&&data.options.passing_grade?data.options.passing_grade:80)+'%</p></div>';
  data.questions.forEach(function(q,i){
    html+='<div style="background:white;border-radius:var(--r);padding:1.1rem;margin-bottom:1rem;box-shadow:var(--shs);border:1px solid var(--border-light)">';
    html+='<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.6rem"><span style="font-size:.76rem;font-weight:700;color:var(--ts)">Pregunta '+(i+1)+' de '+data.total+'</span></div>';
    if(q.image_url)html+='<img src="'+q.image_url+'" style="width:100%;border-radius:8px;margin-bottom:.75rem">';
    html+='<p style="font-size:.97rem;font-weight:700;color:var(--td);margin-bottom:.65rem;line-height:1.4">'+q.title+'</p>';
    if(q.type==='true_false'||q.type==='single_choice'||q.type==='multiple_choice'){
      q.options.forEach(function(o){
        if(!o.title&&!o.image_url)return;
        var inputType=q.type==='multiple_choice'?'checkbox':'radio';
        html+='<label onclick="selectQOpt(this,'+q.id+','+o.id+')" style="display:flex;align-items:center;gap:.75rem;padding:.7rem .85rem;background:var(--vk-cream);border-radius:10px;margin-bottom:.5rem;cursor:pointer;border:1.5px solid transparent;transition:all .15s"><input type="'+inputType+'" name="q_'+q.id+'" value="'+o.id+'" style="width:17px;height:17px;accent-color:var(--vk-rose);flex-shrink:0">'+(o.image_url?'<img src="'+o.image_url+'" style="width:48px;height:48px;object-fit:cover;border-radius:6px;flex-shrink:0">':'')+'<span style="font-size:.93rem;color:var(--td)">'+o.title+'</span></label>';
      });
    }else{
      html+='<input type="text" oninput="_quizAnswers['+q.id+']=this.value" style="width:100%;border:1.5px solid var(--border);border-radius:10px;padding:.75rem;font-family:\'DM Sans\',sans-serif;font-size:.93rem;outline:none;transition:border .15s" placeholder="Tu respuesta..." onfocus="this.style.borderColor=\'var(--vk-rose)\'" onblur="this.style.borderColor=\'var(--border)\'">';
    }
    html+='</div>';
  });
  html+='<button onclick="submitQuiz()" style="width:100%;padding:1rem;border:none;border-radius:var(--rl);background:var(--grad-accent);color:white;font-family:\'DM Sans\',sans-serif;font-size:1rem;font-weight:700;cursor:pointer;box-shadow:0 4px 20px rgba(196,77,138,.3)">Enviar respuestas →</button>';
  document.getElementById('quiz-body').innerHTML=html;
}
function selectQOpt(lbl,qid,val){
  lbl.parentElement.querySelectorAll('label').forEach(function(l){l.style.borderColor='transparent';l.style.background='var(--vk-cream)';});
  lbl.style.borderColor='var(--vk-rose)';lbl.style.background='var(--vk-petal)';
  _quizAnswers[qid]=val;
}
async function submitQuiz(){
  if(!_quiz)return;
  var answers=Object.keys(_quizAnswers).map(function(k){return{question_id:parseInt(k),answer_id:_quizAnswers[k]};});
  showToast('  Enviando...');
  try{
    var tok=ST.token||S.get('vk_token')||'';
    var r=await fetch(C.API_BASE+'/vk/v1/my-quiz-submit',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({vk_token:tok,quiz_id:_quiz.id,course_id:_quiz.courseId,answers:answers})});
    var d=await r.json();
    var passed=d.passed,color=passed?'var(--vk-rose)':'#e07a35';
    document.getElementById('quiz-body').innerHTML='<div style="text-align:center;padding:2rem 1rem"><div style="font-size:3.5rem;margin-bottom:1rem">'+(passed?'':'')+'</div><h2 style="color:'+color+';margin-bottom:.5rem;font-size:1.8rem">'+(passed?'¡Aprobaste!':'Sigue practicando')+'</h2><div style="width:120px;height:120px;border-radius:50%;background:'+color+';display:flex;align-items:center;justify-content:center;margin:1.25rem auto;box-shadow:0 0 0 12px '+(passed?'var(--vk-petal)':'#fff8e6')+'"><span style="font-size:2rem;font-weight:900;color:white">'+d.percentage+'%</span></div><p style="font-size:.95rem;color:var(--ts);margin-bottom:1.5rem">✅ Correctas: <b>'+d.correct+'</b> &nbsp;  Incorrectas: <b>'+d.wrong+'</b></p><button onclick="openCourse('+_quiz.courseId+')" style="background:var(--grad-accent);color:white;border:none;padding:.9rem 2rem;border-radius:14px;font-family:\'DM Sans\',sans-serif;font-size:.97rem;font-weight:700;cursor:pointer">  Volver al curso</button></div>';
  }catch(e){showToast(' Error al enviar');}
}


function escJS(v){return String(v==null?'':v).replace(/\\/g,'\\\\').replace(/'/g,"\\'").replace(/"/g,'&quot;');}
function escHTML(v){return String(v==null?'':v).replace(/[&<>"]/g,function(ch){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[ch];});}
function normalizeCats(raw){
  var arr=[];
  if(Array.isArray(raw))arr=raw;
  else if(raw&&Array.isArray(raw.data))arr=raw.data;
  return arr.map(function(c){
    if(typeof c==='string')return {name:c,slug:c};
    return {name:c.name||c.title||c.label||'Categoría',slug:c.slug||c.term_id||c.id||c.name||'',count:c.count||c.total||c.course_count||c.product_count||''};
  }).filter(function(c){return c.slug!=='';});
}
function buildCatsFromItems(items){
  var map={};
  (items||[]).forEach(function(item){
    var cats=item.categories||item.category||item.product_categories||item.course_categories||[];
    if(typeof cats==='string')cats=[cats];
    if(!Array.isArray(cats))return;
    cats.forEach(function(c){
      var name=(typeof c==='string')?c:(c.name||c.title||c.label||'');
      var slug=(typeof c==='string')?c:(c.slug||c.term_id||c.id||name);
      if(!slug||!name)return;
      if(!map[slug])map[slug]={name:name,slug:slug,count:0};
      map[slug].count++;
    });
  });
  return Object.keys(map).map(function(k){return map[k];});
}

/*  EXPLORAR  */
var _st;
var _courseCats=[];
var _activeCourseCat='';

async function loadCourseCategories(listFallback){
  if(_courseCats.length){renderCourseCategories();return _courseCats;}
  try{
    var r=await fetch(C.API_BASE+'/vk/v1/course-categories');
    var d=await r.json();
    _courseCats=normalizeCats(d);
  }catch(e){_courseCats=[];}
  if(!_courseCats.length&&listFallback)_courseCats=buildCatsFromItems(listFallback);
  renderCourseCategories();
  return _courseCats;
}

function renderCourseCategories(){
  var panel=document.getElementById('course-cat-panel');
  if(!panel)return;
  if(!_courseCats.length){panel.classList.add('is-empty');panel.innerHTML='';return;}
  panel.classList.remove('is-empty');
  var html='<button type="button" onclick="filterCourseCategory(\'\')" class="cat-chip '+(!_activeCourseCat?'active':'')+'">¦ Todos</button>';
  html+=_courseCats.map(function(c){
    var name=escHTML(c.name||'Categoría');
    var slug=String(c.slug||'');
    var count=c.count?'<span class="cat-chip-count">'+c.count+'</span>':'';
    return '<button type="button" onclick="filterCourseCategory(\''+escJS(slug)+'\')" class="cat-chip '+(_activeCourseCat==slug?'active':'')+'">'+name+count+'</button>';
  }).join('');
  panel.innerHTML=html;
}

function toggleCourseFilters(){
  var panel=document.getElementById('course-cat-panel');
  var bg=document.getElementById('course-cat-backdrop');
  if(!panel)return;
  var open=!panel.classList.contains('open');
  panel.classList.toggle('open',open);
  if(bg)bg.classList.toggle('open',open);
}
function closeCourseFilters(){
  var panel=document.getElementById('course-cat-panel');
  var bg=document.getElementById('course-cat-backdrop');
  if(panel)panel.classList.remove('open');
  if(bg)bg.classList.remove('open');
}

async function filterCourseCategory(slug){
  _activeCourseCat=slug||'';
  renderCourseCategories();
  closeCourseFilters();
  await loadAllCourses(true);
}

async function loadAllCourses(keepCategory){
  var el=document.getElementById('search-results');
  var q=(document.getElementById('search-input')||{}).value||'';
  el.innerHTML='<div class="spinner-wrap"><div class="spinner"></div>Cargando...</div>';
  try{
    var params=[];
    if(q.trim())params.push('search='+encodeURIComponent(q.trim()));
    if(_activeCourseCat)params.push('category='+encodeURIComponent(_activeCourseCat));
    var url=C.API_BASE+'/vk/v1/public-courses'+(params.length?'?'+params.join('&'):'');
    var r=await fetch(url);
    var d=await r.json();
    var list=(d&&d.data)?d.data:[];
    await loadCourseCategories(list);
    el.innerHTML=list.length?'<p style="display:none;font-size:.85rem;font-weight:700;color:var(--ts);padding:.5rem .1rem .75rem">'+list.length+' cursos disponibles</p>'+list.map(function(c){return renderCard(c,true);}).join(''):'<p style="text-align:center;color:var(--ts);padding:2rem">Sin resultados</p>';
  }catch(e){el.innerHTML='<div class="error-card"><h4>Error</h4></div>';}
}
function doSearch(q){
  clearTimeout(_st);
  _st=setTimeout(function(){loadAllCourses(true);},350);
}

/*  CURSO PÚBLICO  */
async function openPublicCourse(id){
  _publicCourse={id:id};
  document.getElementById('pub-title-short').textContent='Cargando...';
  document.getElementById('pub-course-hero').innerHTML='<div class="spinner-wrap"><div class="spinner"></div></div>';
  document.getElementById('pub-course-body').innerHTML='<div class="spinner-wrap"><div class="spinner"></div></div>';
  goto('public-course');
  try{
    var c=await getJSON(C.API_BASE+'/vk/v1/public-courses/'+id);
    _publicCourse=c;  // guardar para que enrollCourse acceda a featured_image
    var title=c.post_title||'Curso',img=c.featured_image||'';
    document.getElementById('pub-title-short').textContent=title.length>20?title.substring(0,20)+'...':title;
    var hero=document.getElementById('pub-course-hero');
    if(img)hero.innerHTML='<img src="'+img+'" style="width:100%;height:100%;object-fit:cover">';
    else hero.innerHTML='<span style="font-size:4rem;position:relative;z-index:1"><i class="fas fa-book-open"></i></span>';

    var enrolled=ST.courses&&ST.courses.find(function(x){return x.id==id;});
    var curriculum=c.curriculum||[];
    var html='';

    // Calcular estadísticas
    var totalSec=curriculum.length;
    var totalLes=c.total_lessons||0;
    var totalQz=c.total_quizzes||0;
    var totalVid=curriculum.reduce(function(a,t){
      return a+(t.contents||[]).filter(function(l){return l.video_type&&l.post_type==='lesson';}).length;
    },0);
    var totalFree=curriculum.reduce(function(a,t){
      return a+(t.contents||[]).filter(function(l){return l.is_preview;}).length;
    },0);
    var totalPub=curriculum.reduce(function(a,t){return a+(t.contents?t.contents.length:0);},0);

    // 1. CABECERA
    html+='<div class="pc-header">';
    html+='<h1 class="pc-title">'+title+'</h1>';
    if(c.excerpt)html+='<p class="pc-excerpt">'+c.excerpt+'</p>';
    html+='<div class="pc-meta-row">';
    if(!c.is_free){
      html+='<div class="pc-price-block">';
      html+='<span class="pc-price-main">'+c.price+'</span>';
      if(c.regular_price)html+='<span class="pc-price-old">'+c.regular_price+'</span>';
      html+='</div>';
    }else{
      html+='<span class="pc-price-free"><i class="fas fa-tag"></i> Gratis</span>';
    }
    if(c.level)html+='<span class="pc-level-badge"><i class="fas fa-signal"></i> '+c.level+'</span>';
    html+='</div></div>';

    // 2. VIDEO DE INTRODUCCIÓN (visible siempre que haya uno)
    if(c.intro_video&&c.intro_video.type){
      var iv=c.intro_video,ivSrc='';
      if(iv.type==='youtube'){
        var iyt=iv.url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/);
        ivSrc='<iframe src="https://www.youtube.com/embed/'+(iyt?iyt[1]:iv.url)+'?rel=0" style="position:absolute;inset:0;width:100%;height:100%;border:none" allow="accelerometer;autoplay;clipboard-write;encrypted-media;gyroscope;picture-in-picture" allowfullscreen></iframe>';
      }else if(iv.type==='vimeo'){
        var ivm=iv.url.match(/vimeo\.com\/(\d+)/);
        ivSrc='<iframe src="https://player.vimeo.com/video/'+(ivm?ivm[1]:iv.url)+'" style="position:absolute;inset:0;width:100%;height:100%;border:none" allow="autoplay;fullscreen" allowfullscreen></iframe>';
      }else if(iv.type==='html5'||iv.type==='external'){
        ivSrc='<video controls playsinline style="position:absolute;inset:0;width:100%;height:100%;background:#000" src="'+iv.url+'"></video>';
      }else if(iv.type==='embedded'&&iv.embed){
        ivSrc=iv.embed.replace(/width="[^"]*"/,'width="100%"').replace(/height="[^"]*"/,'height="100%"').replace(/<iframe/,'<iframe style="position:absolute;inset:0;width:100%;height:100%;border:none"');
      }
      if(ivSrc){
        html+='<div class="cd-intro-video" style="margin:0 0 1.25rem">'
          +'<div class="cd-intro-label"><i class="fas fa-circle-play"></i> Video de introducción</div>'
          +'<div style="position:relative;width:100%;aspect-ratio:16/9;background:#000">'+ivSrc+'</div>'
          +'</div>';
      }
    }

    // 3. CTA PRINCIPAL
    var previewEnrolled=ST.courses&&ST.courses.find(function(x){return x.id==id&&x.is_preview_enrolled;});
    html+='<div class="pc-cta-top">';
    if(enrolled&&!previewEnrolled){
      html+='<button class="pc-btn-primary pc-btn-continue" onclick="openCourse('+id+')"><i class="fas fa-circle-play"></i> Continuar mi curso</button>';
    }else if(previewEnrolled){
      html+='<button class="pc-btn-primary pc-btn-continue" onclick="openCourse('+id+')"><i class="fas fa-play-circle"></i> Ver clases gratuitas</button>';
      if(!c.is_free){
        html+='<button class="pc-btn-secondary pc-btn-buy" style="margin-top:.55rem" onclick="document.getElementById(\'pc-buy-block\').scrollIntoView({behavior:\'smooth\'})"><i class="fas fa-lock-open"></i> Acceder al curso completo &middot; '+c.price+'</button>';
      }
    }else if(c.is_free){
      html+='<button class="pc-btn-primary" onclick="enrollCourse('+id+')" id="btn-enroll"><i class="fas fa-graduation-cap"></i> Inscribirme gratis</button>';
    }else if(totalFree>0){
      html+='<button class="pc-btn-preview" onclick="previewEnrollCourse('+id+')" id="btn-preview-enroll"><i class="fas fa-play"></i> Probar gratis ('+totalFree+' '+(totalFree===1?'lección':'lecciones')+')</button>';
      html+='<button class="pc-btn-primary pc-btn-buy" style="margin-top:.55rem" onclick="document.getElementById(\'pc-buy-block\').scrollIntoView({behavior:\'smooth\'})"><i class="fas fa-credit-card"></i> Acceso completo &middot; '+c.price+'</button>';
    }else{
      html+='<button class="pc-btn-primary pc-btn-buy" onclick="document.getElementById(\'pc-buy-block\').scrollIntoView({behavior:\'smooth\'})"><i class="fas fa-credit-card"></i> Obtener acceso completo &middot; '+c.price+'</button>';
    }
    html+='</div>';

    // 3. GRID DE ESTADÍSTICAS
    html+='<div class="pc-stats-grid">';
    if(totalSec)  html+='<div class="pc-stat-item"><i class="fas fa-layer-group pc-stat-icon"></i><span class="pc-stat-val">'+totalSec+'</span><span class="pc-stat-lbl">'+(totalSec===1?'módulo':'módulos')+'</span></div>';
    if(totalLes)  html+='<div class="pc-stat-item"><i class="fas fa-play-circle pc-stat-icon"></i><span class="pc-stat-val">'+totalLes+'</span><span class="pc-stat-lbl">'+(totalLes===1?'lección':'lecciones')+'</span></div>';
    if(totalQz)   html+='<div class="pc-stat-item"><i class="fas fa-clipboard-list pc-stat-icon"></i><span class="pc-stat-val">'+totalQz+'</span><span class="pc-stat-lbl">'+(totalQz===1?'examen':'exámenes')+'</span></div>';
    if(c.duration)html+='<div class="pc-stat-item"><i class="fas fa-clock pc-stat-icon"></i><span class="pc-stat-val">'+c.duration+'</span><span class="pc-stat-lbl">duración</span></div>';
    if(totalFree) html+='<div class="pc-stat-item"><i class="fas fa-play pc-stat-icon pc-stat-free"></i><span class="pc-stat-val">'+totalFree+'</span><span class="pc-stat-lbl">gratis</span></div>';
    if(c.has_certificate)html+='<div class="pc-stat-item"><i class="fas fa-certificate pc-stat-icon pc-stat-cert"></i><span class="pc-stat-val">✓</span><span class="pc-stat-lbl">certificado</span></div>';
    html+='</div>';

    // 4. LO QUE APRENDERÁS
    var wwl=c.what_will_learn||[];
    if(wwl.length){
      html+='<div class="pc-section">';
      html+='<h2 class="pc-section-title"><i class="fas fa-graduation-cap"></i> Lo que aprenderás</h2>';
      html+='<div class="pc-learn-grid">';
      wwl.forEach(function(item){
        html+='<div class="pc-learn-item"><i class="fas fa-check pc-check-icon"></i><span>'+item+'</span></div>';
      });
      html+='</div></div>';
    }

    // 5. DESCRIPCIÓN HTML COMPLETA
    var descHtml=c.post_content||'';
    if(descHtml&&descHtml.trim()){
      html+='<div class="pc-section">';
      html+='<h2 class="pc-section-title"><i class="fas fa-info-circle"></i> Descripción del curso</h2>';
      html+='<div class="pc-desc-wrap" id="pub-desc-wrap"><div class="pc-desc" id="pub-desc">'+descHtml+'</div></div>';
      html+='<button class="pc-desc-toggle" id="pub-desc-toggle" onclick="pubToggleDesc()"><i class="fas fa-chevron-down"></i> Ver descripción completa</button>';
      html+='</div>';
    }else if(c.excerpt){
      html+='<div class="pc-section"><p class="pc-desc">'+c.excerpt+'</p></div>';
    }

    // 6. REQUISITOS
    var reqs=c.requirements||[];
    if(reqs.length){
      html+='<div class="pc-section">';
      html+='<h2 class="pc-section-title"><i class="fas fa-list-check"></i> Requisitos</h2>';
      html+='<ul class="pc-req-list">';
      reqs.forEach(function(item){html+='<li><i class="fas fa-circle-dot"></i><span>'+item+'</span></li>';});
      html+='</ul></div>';
    }

    // 7. CURRICULUM ACORDEÓN
    if(curriculum.length){
      html+='<div class="pc-section pc-curriculum-section">';
      html+='<div class="pc-curriculum-header">';
      html+='<h2 class="pc-section-title" style="margin:0"><i class="fas fa-book-open"></i> Contenido del curso</h2>';
      html+='<span class="pc-curriculum-summary">'+totalSec+' mód. &middot; '+totalPub+' lec.'+(totalQz?' &middot; '+totalQz+' ex.':'')+'</span>';
      html+='</div>';

      curriculum.forEach(function(t,ti){
        var tCount=t.contents?t.contents.length:0;
        var tPrev=t.contents?t.contents.filter(function(l){return l.is_preview;}).length:0;
        var isOpen=(ti===0);
        html+='<div class="pc-topic" id="pub-sec-'+ti+'">';
        html+='<div class="pc-topic-header" onclick="pubToggleSection('+ti+')">';
        html+='<div class="pc-topic-left">';
        html+='<i class="fas fa-chevron-down pc-topic-chev'+(isOpen?' rotated':'')+'" id="pub-sec-chev-'+ti+'"></i>';
        html+='<span class="pc-topic-title">'+(t.post_title||'Módulo '+(ti+1))+'</span>';
        html+='</div>';
        html+='<span class="pc-topic-meta">'+tCount+' lec.'+(tPrev?' &middot; <span class="pc-free-count">'+tPrev+' gratis</span>':'')+'</span>';
        html+='</div>';
        html+='<div class="pc-topic-lessons'+(isOpen?' open':'')+'" id="pub-lessons-'+ti+'">';
        (t.contents||[]).forEach(function(l){
          var ltit=l.post_title||'Lección';
          var isPreview=!!l.is_preview;
          var isQ=l.post_type==='tutor_quiz';
          var safe=ltit.replace(/'/g,"\\'").replace(/"/g,'&quot;');
          var dur=l.duration||'';
          var typeIcon=isQ
            ?'<i class="fas fa-clipboard-list pc-li-icon pc-li-quiz"></i>'
            :(l.video_type==='youtube'||l.video_type==='vimeo'
              ?'<i class="fab fa-youtube pc-li-icon pc-li-video"></i>'
              :'<i class="fas fa-play-circle pc-li-icon"></i>');
          if(isPreview){
            html+='<div class="pc-lesson-row pc-lesson-free" onclick="openLesson('+l.id+',\''+safe+'\','+id+',\''+l.post_type+'\')">';
            html+=typeIcon;
            html+='<span class="pc-lesson-title pc-lesson-title-free">'+ltit+'</span>';
            html+='<div class="pc-lesson-right">';
            if(dur)html+='<span class="pc-lesson-dur">'+dur+'</span>';
            html+='<span class="pc-badge-free"><i class="fas fa-play"></i> Gratis</span>';
            html+='</div></div>';
          }else{
            html+='<div class="pc-lesson-row pc-lesson-locked">';
            html+=typeIcon;
            html+='<span class="pc-lesson-title">'+ltit+'</span>';
            html+='<div class="pc-lesson-right">';
            if(dur)html+='<span class="pc-lesson-dur">'+dur+'</span>';
            html+='<i class="fas fa-lock pc-lock-icon"></i>';
            html+='</div></div>';
          }
        });
        html+='</div></div>';
      });

      // Bloque de compra: visible para no inscritos Y para preview-enrolled (aún no pagaron)
      var showBuyBlock=!c.is_free&&(!enrolled||!!previewEnrolled);
      if(showBuyBlock){
        var lockedCount=previewEnrolled?(totalPub-totalFree):totalPub;
        var waMsg=buildWaEnrollMsg(title,c.price||'');
        html+='<div class="pc-buy-block" id="pc-buy-block">';
        html+='<div class="pc-buy-header">';
        html+='<div class="pc-buy-lock"><i class="fas fa-lock-open"></i></div>';
        html+='<div>';
        if(previewEnrolled){
          html+='<p class="pc-buy-title">Desbloquea '+lockedCount+' lecciones premium</p>';
          html+='<p class="pc-buy-sub">Ya tienes las '+totalFree+' gratuitas — completa el acceso</p>';
        }else{
          html+='<p class="pc-buy-title">Obtén acceso completo</p>';
          html+='<p class="pc-buy-sub">'+totalPub+' lecciones &middot; Acceso de por vida &middot; '+c.price+'</p>';
        }
        html+='</div></div>';
        html+='<p class="pc-buy-price">'+c.price+'</p>';
        if(c.payment_link&&c.payment_link.trim())
          html+='<a class="pc-pay-btn pc-pay-mp" href="'+c.payment_link+'" target="_blank"><i class="fas fa-credit-card"></i> Pagar con Mercado Pago</a>';
        if(c.paypal_link&&c.paypal_link.trim())
          html+='<a class="pc-pay-btn pc-pay-pp" href="'+c.paypal_link+'" target="_blank"><i class="fab fa-paypal"></i> Pagar con PayPal</a>';
        html+='<a class="pc-pay-btn pc-pay-wa" href="'+buildWaLink(waMsg)+'" target="_blank"><i class="fab fa-whatsapp"></i> Consultar por WhatsApp</a>';
        html+='</div>';
      }
      html+='</div>';
    }

    // 8. CTA FINAL para gratis
    if(!enrolled&&c.is_free){
      html+='<div class="pc-cta-bottom"><button class="pc-btn-primary" onclick="enrollCourse('+id+')" id="btn-enroll2"><i class="fas fa-graduation-cap"></i> Inscribirme gratis</button></div>';
    }

    document.getElementById('pub-course-body').innerHTML=html;

    var descEl=document.getElementById('pub-desc');
    var toggleBtn=document.getElementById('pub-desc-toggle');
    if(descEl&&toggleBtn&&descEl.scrollHeight<=220){toggleBtn.style.display='none';}

  }catch(e){
    console.error('[openPublicCourse]',e);
    var _isNet=!navigator.onLine||e.message==='Failed to fetch'||String(e).includes('fetch');
    var _emsg=_isNet?'Sin conexión a internet. Verifica tu red e intenta de nuevo.':'No se pudo cargar el contenido. Intenta recargar la app.';
    document.getElementById('pub-course-body').innerHTML='<div class="error-card" style="text-align:center;padding:2rem"><h4>'+(_isNet?'Sin conexión':'Error al cargar')+'</h4><p style="font-size:.85rem;margin-top:.5rem;color:#888">'+_emsg+'</p><button onclick="location.reload()" style="margin-top:.75rem;padding:.55rem 1.25rem;background:#1b4332;color:#fff;border:none;border-radius:10px;font-weight:700;cursor:pointer">'+(_isNet?'Reintentar':'Recargar app')+'</button></div>';
  }
}


function pubToggleSection(ti){
  var lessons=document.getElementById('pub-lessons-'+ti);
  var chev=document.getElementById('pub-sec-chev-'+ti);
  if(!lessons)return;
  var opening=!lessons.classList.contains('open');
  lessons.classList.toggle('open',opening);
  if(chev){chev.className='fas fa-chevron-down pc-topic-chev'+(opening?' rotated':'');}
}
function pubToggleDesc(){
  var wrap=document.getElementById('pub-desc-wrap');
  var btn=document.getElementById('pub-desc-toggle');
  if(!wrap)return;
  var expanded=wrap.classList.toggle('pc-desc-expanded');
  if(btn)btn.innerHTML='<i class="fas fa-chevron-'+(expanded?'up':'down')+'"></i> '+(expanded?'Ver menos':'Ver descripción completa');
}

async function enrollCourse(id){
  if(!ST.user||!ST.user.id){showToast('Inicia sesion primero');goto('login');return;}
  var btn=document.getElementById('btn-enroll');
  if(btn){btn.textContent='Inscribiendo...';btn.disabled=true;}
  try{
    var tok=ST.token||S.get('vk_token')||'';

    // Timeout de 10s — si el servidor tarda más, es error de red
    var controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
    var timer = controller ? setTimeout(function(){ controller.abort(); }, 10000) : null;

    var fetchOpts = {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({course_id:id}),
    };
    if (controller) fetchOpts.signal = controller.signal;

    var er = await fetch(C.API_BASE+'/vk/v1/enroll-course?vk_token='+encodeURIComponent(tok), fetchOpts);
    if (timer) clearTimeout(timer);

    var edata={};
    try{ edata = await er.json(); }catch(x){}

    if(!er.ok){
      showToast(edata.message||'Error al inscribirse ('+er.status+')');
      if(btn){btn.textContent='Inscribirme gratis';btn.disabled=false;}
      return;
    }

    // ── Inscripción exitosa ─────────────────────────────────────────
    var courseTitle = edata.course_title || 'Curso';
    showToast(edata.already ? 'Ya estas inscrito en este curso' : 'Inscripcion exitosa en ' + courseTitle);

    // Agregar el curso al estado LOCAL inmediatamente (sin esperar al servidor)
    if(!ST.courses) ST.courses=[];
    // Imagen: del response del API, o de la pantalla pública ya cargada
    var _pubImg = edata.featured_image || (_publicCourse && (_publicCourse.featured_image||_publicCourse.thumbnail)) || '';
    var _pubTotal = (_publicCourse && _publicCourse.total_lessons) || 0;
    if(!Array.isArray(ST.courses))ST.courses=[];
    if(!ST.courses.find(function(x){return x.id==id;})){
      ST.courses.push({
        id:id, post_title:courseTitle, completed_percent:0,
        total_lessons:_pubTotal, enroll_status:'enrolled', featured_image:_pubImg
      });
    } else {
      // Ya existía (ej: preview-enrolled) → actualizar imagen si estaba vacía
      var _ex=ST.courses.find(function(x){return x.id==id;});
      if(_ex && !_ex.featured_image && _pubImg) _ex.featured_image=_pubImg;
      if(_ex) _ex.enroll_status='enrolled';
    }

    // Abrir el curso de inmediato
    openCourse(id);

    // Actualizar lista de cursos en segundo plano para sincronizar con el servidor
    setTimeout(async function(){
      try{
        var cdata = await _fetchWithRetry(apiURL('/vk/v1/my-courses')+'&_='+Date.now());
        var list = (cdata&&Array.isArray(cdata.data))?cdata.data:[];
        if(list.length){ST.courses=list;_cacheSet(apiURL('/vk/v1/my-courses'),cdata,120000);}
        if(typeof loadHomePreview==='function') loadHomePreview();
      }catch(e){}
    }, 1000);

  }catch(e){
    var msg = (e && e.name === 'AbortError') ? 'Tiempo de espera agotado. Intenta de nuevo.' : 'Error al inscribirse';
    showToast(msg);
    if(btn){btn.textContent='Inscribirme gratis';btn.disabled=false;}
  }
}

/*  PRODUCTOS  */
var _productCats=[];
var _activeProductCat='';
var _productSearchTimer;

async function loadProductCategories(listFallback){
  if(_productCats.length){renderProductCategories();return _productCats;}
  try{
    var r=await fetch(C.API_BASE+'/vk/v1/product-categories');
    var d=await r.json();
    _productCats=normalizeCats(d);
  }catch(e){_productCats=[];}
  if(!_productCats.length&&listFallback)_productCats=buildCatsFromItems(listFallback);
  renderProductCategories();
  return _productCats;
}
function renderProductCategories(){
  var panel=document.getElementById('product-cat-panel');
  if(!panel)return;
  if(!_productCats.length){panel.classList.add('is-empty');panel.innerHTML='';return;}
  panel.classList.remove('is-empty');
  var html='<button type="button" onclick="filterProductCategory(\'\')" class="cat-chip '+(!_activeProductCat?'active':'')+'">¦ Todos</button>';
  html+=_productCats.map(function(c){
    var name=escHTML(c.name||'Categoría');
    var slug=String(c.slug||'');
    var count=c.count?'<span class="cat-chip-count">'+c.count+'</span>':'';
    return '<button type="button" onclick="filterProductCategory(\''+escJS(slug)+'\')" class="cat-chip '+(_activeProductCat==slug?'active':'')+'">'+name+count+'</button>';
  }).join('');
  panel.innerHTML=html;
}
function toggleProductFilters(){
  var panel=document.getElementById('product-cat-panel');
  var bg=document.getElementById('product-cat-backdrop');
  if(!panel)return;
  var open=!panel.classList.contains('open');
  panel.classList.toggle('open',open);
  if(bg)bg.classList.toggle('open',open);
}
function closeProductFilters(){
  var panel=document.getElementById('product-cat-panel');
  var bg=document.getElementById('product-cat-backdrop');
  if(panel)panel.classList.remove('open');
  if(bg)bg.classList.remove('open');
}
async function filterProductCategory(slug){
  _activeProductCat=slug||'';
  renderProductCategories();
  closeProductFilters();
  await loadProducts(true);
}
function doProductSearch(q){
  clearTimeout(_productSearchTimer);
  _productSearchTimer=setTimeout(function(){loadProducts(true);},350);
}
async function loadProducts(){
  var el=document.getElementById('products-list');
  var q=(document.getElementById('product-search-input')||{}).value||'';
  el.innerHTML='<div class="spinner-wrap"><div class="spinner"></div>Cargando...</div>';
  try{
    var params=[];
    if(q.trim())params.push('search='+encodeURIComponent(q.trim()));
    if(_activeProductCat)params.push('category='+encodeURIComponent(_activeProductCat));
    var r=await fetch(C.API_BASE+'/vk/v1/products'+(params.length?'?'+params.join('&'):''));
    var d=await r.json();
    var list=(d&&d.data)?d.data:[];
    await loadProductCategories(list);
    if(!list.length){el.innerHTML='<p style="text-align:center;color:var(--ts);padding:2rem">Sin productos disponibles</p>';return;}
    el.innerHTML=list.map(function(p){
      var img=p.image||p.featured_image||'';
      var cats=p.categories&&p.categories.length
        ?'<div class="pk-cats">'+p.categories.slice(0,2).map(function(c){return '<span class="pk-cat">'+escHTML(c.name||c)+'</span>';}).join('')+'</div>':'';
      var thumb=img
        ?'<div class="product-thumb"><img src="'+escHTML(img)+'" onerror="_imgFallback(this)" alt="'+escHTML(p.title)+'"></div>'
        :'<div class="product-thumb"><i class="fas fa-box-open" style="font-size:1.6rem;color:var(--vk-plum);opacity:.4"></i></div>';
      return '<div class="product-card" onclick="openProductDetail('+p.id+')">'
        +thumb
        +'<div style="flex:1;min-width:0">'
        +cats
        +'<h3 class="product-card-title">'+escHTML(p.title)+'</h3>'
        +'<p class="product-card-excerpt">'+escHTML((p.excerpt||'').substring(0,80))+'</p>'
        +'<p class="product-price">'+(p.is_free?'Gratis':escHTML(p.price||''))+'</p>'
        +'</div>'
        +'<span style="color:var(--tu);font-size:1rem;flex-shrink:0"><i class="fas fa-chevron-right"></i></span>'
        +'</div>';
    }).join('');
  }catch(e){el.innerHTML='<div class="error-card"><h4>Error cargando productos</h4></div>';}
}
var _pkGallery=[], _pkGalleryIdx=0;

/* ── Galería de producto ────────────────────────────────────────── */
/* ── Procesa [embed]URL[/embed] y URLs de video sueltas en HTML de producto ── */
function pkProcessEmbeds(html) {
  if (!html) return html;

  function videoIframe(url) {
    url = url.trim();
    // YouTube
    var yt = url.match(/(?:youtube\.com\/(?:watch\?v=|embed\/|shorts\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/);
    if (yt) {
      return '<div class="pk-video-wrap"><iframe src="https://www.youtube.com/embed/' + yt[1] + '?rel=0" allowfullscreen allow="accelerometer;autoplay;clipboard-write;encrypted-media;gyroscope;picture-in-picture" loading="lazy" title="Video"></iframe></div>';
    }
    // Vimeo
    var vm = url.match(/vimeo\.com\/(\d+)/);
    if (vm) {
      return '<div class="pk-video-wrap"><iframe src="https://player.vimeo.com/video/' + vm[1] + '" allowfullscreen allow="autoplay;fullscreen" loading="lazy" title="Video"></iframe></div>';
    }
    // MP4 / WebM directo
    if (url.match(/\.(mp4|webm|ogg)(\?|$)/i)) {
      return '<div class="pk-video-wrap"><video controls playsinline preload="metadata" src="' + url + '"></video></div>';
    }
    // Fallback: enlace clicable
    return '<a href="' + url + '" target="_blank" rel="noopener" class="pk-video-link"><i class="fas fa-play-circle"></i> Ver video</a>';
  }

  // 1. [embed]URL[/embed]  (shortcode de WordPress)
  html = html.replace(/\[embed\]([\s\S]*?)\[\/embed\]/gi, function(_, url) {
    return videoIframe(url);
  });

  // 2. <p>URL-de-video-sola</p>  (WordPress auto-embed)
  html = html.replace(/<p>\s*(https?:\/\/(?:(?:www\.)?youtube\.com\/watch[^\s<]*|youtu\.be\/[^\s<]*|(?:www\.)?vimeo\.com\/\d+[^\s<]*))\s*<\/p>/gi, function(_, url) {
    return videoIframe(url);
  });

  return html;
}

function pkRenderGallery(gallery, heroEl) {
  window._prodGallery   = gallery;
  window._prodGalleryIdx = 0;
  _pkGallery  = gallery;
  _pkGalleryIdx = 0;

  if (!gallery.length) {
    heroEl.innerHTML = '<div class="pk-empty"><i class="fas fa-box-open"></i></div>';
    return;
  }

  var counter = gallery.length > 1
    ? '<div style="position:absolute;bottom:10px;right:12px;background:rgba(0,0,0,.55);backdrop-filter:blur(6px);border-radius:20px;padding:.2rem .65rem;font-size:.72rem;color:white;font-weight:600"><i class="far fa-images" style="margin-right:.25rem"></i>' + gallery.length + '</div>'
    : '';

  var arrows = gallery.length > 1
    ? '<div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:space-between;padding:0 8px;pointer-events:none">'
        + '<button onclick="event.stopPropagation();pgPrev()" class="pg-arrow">&#8249;</button>'
        + '<button onclick="event.stopPropagation();pgNext()" class="pg-arrow">&#8250;</button>'
      + '</div>'
    : '';

  var thumbsHtml = gallery.length > 1
    ? '<div class="pg-thumbs">'
        + gallery.map(function(g, i) {
            return '<img src="' + escHTML(g.url) + '" class="pg-thumb' + (i === 0 ? ' active' : '') + '"'
              + ' onclick="pgSelect(' + i + ')" loading="lazy" alt="' + escHTML(g.alt || '') + '"'
              + ' onerror="_imgFallback(this)">';
          }).join('')
      + '</div>'
    : '';

  heroEl.innerHTML =
    '<div id="pg-main-wrap" onclick="pkLightbox(window._prodGalleryIdx)" style="cursor:zoom-in">'
      + '<img id="pg-main-img" src="' + escHTML(gallery[0].url) + '" onerror="_imgFallback(this)" alt="' + escHTML(gallery[0].alt || '') + '">'
      + counter
      + arrows
    + '</div>'
    + thumbsHtml;
}

/* ── Controles de galería ───────────────────────────────────────── */
function pgSelect(idx) {
  if (!window._prodGallery || !window._prodGallery[idx]) return;
  window._prodGalleryIdx = idx;
  _pkGalleryIdx = idx;
  var img = document.getElementById('pg-main-img');
  if (img) { img.src = window._prodGallery[idx].url; img.alt = window._prodGallery[idx].alt || ''; }
  document.querySelectorAll('.pg-thumb').forEach(function(t, i) { t.classList.toggle('active', i === idx); });
}
function pgPrev() { pgSelect(((window._prodGalleryIdx || 0) - 1 + (window._prodGallery || []).length) % (window._prodGallery || [1]).length); }
function pgNext() { pgSelect(((window._prodGalleryIdx || 0) + 1) % (window._prodGallery || [1]).length); }

/* ── Lightbox de galería con navegación completa ────────────────── */
function pkLightbox(startIdx) {
  var gallery = _pkGallery;
  if (!gallery || !gallery.length) return;
  var idx = ((startIdx || 0) + gallery.length) % gallery.length;

  var existing = document.getElementById('pk-lightbox');
  if (existing) existing.remove();

  var lb = document.createElement('div');
  lb.id = 'pk-lightbox';

  if (!document.getElementById('pk-lb-css')) {
    var s = document.createElement('style');
    s.id = 'pk-lb-css';
    s.textContent =
      '#pk-lightbox{position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,.95);display:flex;flex-direction:column;align-items:center;justify-content:center;animation:pkLbIn .2s ease}'
      + '@keyframes pkLbIn{from{opacity:0}to{opacity:1}}'
      + '#pk-lightbox .pk-lb-img-wrap{flex:1;display:flex;align-items:center;justify-content:center;width:100%;overflow:hidden;padding:3.5rem 0 1rem}'
      + '#pk-lightbox .pk-lb-img-wrap img{max-width:96vw;max-height:82vh;object-fit:contain;border-radius:8px;box-shadow:0 8px 48px rgba(0,0,0,.6);user-select:none;animation:pkLbImg .22s cubic-bezier(.34,1.4,.64,1)}'
      + '@keyframes pkLbImg{from{transform:scale(.9);opacity:.6}to{transform:scale(1);opacity:1}}'
      + '#pk-lightbox .pk-lb-close{position:fixed;top:1rem;right:1rem;width:40px;height:40px;border-radius:50%;background:rgba(255,255,255,.15);backdrop-filter:blur(6px);border:none;color:#fff;font-size:1.2rem;cursor:pointer;display:flex;align-items:center;justify-content:center;z-index:2}'
      + '#pk-lightbox .pk-lb-nav{position:fixed;top:50%;transform:translateY(-50%);background:rgba(255,255,255,.15);backdrop-filter:blur(6px);border:none;color:#fff;width:44px;height:44px;border-radius:50%;font-size:1.1rem;cursor:pointer;display:flex;align-items:center;justify-content:center;z-index:2;transition:background .15s}'
      + '#pk-lightbox .pk-lb-nav:hover{background:rgba(255,255,255,.28)}'
      + '#pk-lightbox .pk-lb-prev{left:1rem}'
      + '#pk-lightbox .pk-lb-next{right:1rem}'
      + '#pk-lightbox .pk-lb-counter{position:fixed;top:1.1rem;left:50%;transform:translateX(-50%);color:rgba(255,255,255,.7);font-size:.85rem;font-family:system-ui,sans-serif;background:rgba(0,0,0,.4);padding:.2rem .75rem;border-radius:999px}'
      + '#pk-lightbox .pk-lb-thumbs{display:flex;gap:.4rem;padding:.5rem 1rem;overflow-x:auto;max-width:96vw;-webkit-overflow-scrolling:touch}'
      + '#pk-lightbox .pk-lb-thumbs::-webkit-scrollbar{display:none}'
      + '#pk-lightbox .pk-lb-thumb{width:52px;height:52px;flex-shrink:0;border-radius:7px;overflow:hidden;cursor:pointer;border:2.5px solid transparent;opacity:.55;transition:opacity .15s,border-color .15s}'
      + '#pk-lightbox .pk-lb-thumb.active{border-color:#fff;opacity:1}'
      + '#pk-lightbox .pk-lb-thumb img{width:100%;height:100%;object-fit:cover}';
    document.head.appendChild(s);
  }

  lb.innerHTML =
    '<button class="pk-lb-close" aria-label="Cerrar">&#x2715;</button>'
    + '<div class="pk-lb-counter" id="pk-lb-counter"></div>'
    + (gallery.length > 1 ? '<button class="pk-lb-nav pk-lb-prev" aria-label="Anterior"><i class="fas fa-chevron-left"></i></button><button class="pk-lb-nav pk-lb-next" aria-label="Siguiente"><i class="fas fa-chevron-right"></i></button>' : '')
    + '<div class="pk-lb-img-wrap"><img id="pk-lb-img" src="" alt=""></div>'
    + (gallery.length > 1
      ? '<div class="pk-lb-thumbs">'
          + gallery.map(function(g, i) {
            return '<div class="pk-lb-thumb" data-i="' + i + '"><img src="' + escHTML(g.url) + '" alt=""></div>';
          }).join('') + '</div>'
      : '');

  document.body.appendChild(lb);

  function setImg(i) {
    idx = ((i % gallery.length) + gallery.length) % gallery.length;
    var imgEl = document.getElementById('pk-lb-img');
    if (imgEl) { imgEl.src = escHTML(gallery[idx].url); imgEl.alt = gallery[idx].alt || ''; }
    var ctr = document.getElementById('pk-lb-counter');
    if (ctr) ctr.textContent = (idx + 1) + ' / ' + gallery.length;
    lb.querySelectorAll('.pk-lb-thumb').forEach(function(t) {
      var ti = parseInt(t.getAttribute('data-i'), 10);
      t.classList.toggle('active', ti === idx);
      if (ti === idx) t.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
    });
  }

  function close() { lb.style.transition = 'opacity .15s'; lb.style.opacity = '0'; setTimeout(function() { lb.remove(); }, 150); }

  lb.querySelector('.pk-lb-close').addEventListener('click', close);
  if (gallery.length > 1) {
    lb.querySelector('.pk-lb-prev').addEventListener('click', function() { setImg(idx - 1); });
    lb.querySelector('.pk-lb-next').addEventListener('click', function() { setImg(idx + 1); });
  }
  lb.querySelectorAll('.pk-lb-thumb').forEach(function(t) {
    t.addEventListener('click', function() { setImg(parseInt(t.getAttribute('data-i'), 10)); });
  });
  lb.addEventListener('click', function(e) { if (e.target === lb) close(); });

  // Swipe táctil
  var sx2 = 0, sy2 = 0;
  lb.addEventListener('touchstart', function(e) { sx2 = e.touches[0].clientX; sy2 = e.touches[0].clientY; }, { passive: true });
  lb.addEventListener('touchend', function(e) {
    var dx = e.changedTouches[0].clientX - sx2;
    var dy = e.changedTouches[0].clientY - sy2;
    if (Math.abs(dx) > Math.abs(dy) && Math.abs(dx) > 40) setImg(idx + (dx < 0 ? 1 : -1));
  }, { passive: true });

  // Teclado
  function onKey(e) { if (e.key === 'ArrowLeft') setImg(idx - 1); else if (e.key === 'ArrowRight') setImg(idx + 1); else if (e.key === 'Escape') close(); }
  document.addEventListener('keydown', onKey);
  lb.addEventListener('remove', function() { document.removeEventListener('keydown', onKey); });
  // Limpiar listener al cerrar
  var origClose = close;
  close = function() { document.removeEventListener('keydown', onKey); origClose(); };
  lb.querySelector('.pk-lb-close').onclick = close;
  lb.onclick = function(e) { if (e.target === lb) close(); };

  setImg(idx);
}

async function openProductDetail(id){
  var _pts=document.getElementById('prod-title-short');if(_pts)_pts.textContent='Cargando...';
  document.getElementById('prod-hero').innerHTML='<div class="spinner-wrap"><div class="spinner"></div></div>';
  document.getElementById('prod-body').innerHTML='<div class="spinner-wrap"><div class="spinner"></div></div>';
  goto('product-detail');
  try{
    var r=await fetch(C.API_BASE+'/vk/v1/products/'+id);
    var p=await r.json();
    if(_pts)_pts.textContent=(p.title||'Producto').substring(0,22);

    // ── Galería ──────────────────────────────────────────────────────
    var gallery=p.gallery&&p.gallery.length?p.gallery:[];
    var fallbackImg=p.image||p.featured_image||(p.linked_course&&p.linked_course.featured_image)||'';
    if(!gallery.length&&fallbackImg) gallery=[{url:fallbackImg,alt:p.title||''}];

    pkRenderGallery(gallery, document.getElementById('prod-hero'));

    // ── Cuerpo del detalle ───────────────────────────────────────────
    var courseTitle=(p.linked_course&&p.linked_course.post_title)?p.linked_course.post_title:p.title;
    var waMsg=buildWaEnrollMsg(courseTitle,p.price||'');
    var waURL=buildWaLink(waMsg);
    var yaInscrito=p.linked_course_id&&ST.courses&&ST.courses.find(function(x){return x.id==p.linked_course_id;});

    var cats=p.categories&&p.categories.length
      ?'<div class="pk-cats" style="margin-bottom:.7rem">'+p.categories.map(function(c){return '<span class="pk-cat">'+escHTML(c.name||c)+'</span>';}).join('')+'</div>':'';

    var priceHtml=p.is_free
      ?'<div class="pkd-price free">Gratis</div>'
      :'<div class="pkd-price">'+escHTML(p.price||'')+'</div>';

    var shortDesc=p.short_description?'<div class="pkd-short-desc">'+pkProcessEmbeds(p.short_description)+'</div>':'';
    var fullDesc=p.description?'<div class="pkd-desc">'+pkProcessEmbeds(p.description)+'</div>':'';

    var courseBox='';
    if(p.linked_course_id&&p.linked_course){
      var lc=p.linked_course;
      courseBox='<div class="pkd-course-box">'
        +'<div class="pkd-course-icon"><i class="fas fa-graduation-cap"></i></div>'
        +'<div class="pkd-course-info">'
        +'<p class="pkd-course-label">Curso incluido</p>'
        +'<p class="pkd-course-title">'+escHTML(lc.post_title)+'</p>'
        +(lc.total_lessons?'<p class="pkd-course-meta"><i class="fas fa-play-circle"></i> '+lc.total_lessons+' lecciones · Acceso inmediato</p>':'')
        +'</div></div>';
    }

    var actionsHtml='<div class="pkd-actions">';
    if(yaInscrito){
      actionsHtml+='<button class="pkd-btn-primary" onclick="openCourse('+p.linked_course_id+')">'
        +'<i class="fa-solid fa-circle-chevron-right"></i> Ir al curso ahora</button>';
    } else {
      if(p.mercado_pago_link&&p.mercado_pago_link.trim())
        actionsHtml+='<button class="btn-mercado-pago pkd-btn-full" onclick="window.open(\''+escHTML(p.mercado_pago_link)+'\',\'_blank\')"><i class="fas fa-credit-card"></i> Pagar con Mercado Pago</button>';
      if(p.paypal_link&&p.paypal_link.trim())
        actionsHtml+='<button class="btn-paypal pkd-btn-full" onclick="window.open(\''+escHTML(p.paypal_link)+'\',\'_blank\')"><i class="fab fa-paypal"></i> Pagar con PayPal</button>';
      actionsHtml+='<button class="btn-whatsapp pkd-btn-full" onclick="window.open(\''+waURL+'\',\'_blank\')"><i class="fab fa-whatsapp"></i> Consultar por WhatsApp</button>';
    }
    if(p.wc_permalink||p.permalink)
      actionsHtml+='<button class="pkd-btn-web" onclick="window.open(\''+escHTML(p.wc_permalink||p.permalink)+'\',\'_blank\')"><i class="fas fa-globe"></i> Comprar en la web</button>';
    actionsHtml+='</div>';
    actionsHtml+='<p class="pkd-trust"><i class="fas fa-shield-alt"></i> Pago seguro · <i class="fas fa-bolt"></i> Acceso inmediato al confirmar</p>';

    document.getElementById('prod-body').innerHTML=
      '<div class="pkd-body">'
      +cats
      +'<h1 class="pkd-title">'+escHTML(p.title)+'</h1>'
      +priceHtml
      +shortDesc
      +courseBox
      +fullDesc
      +'<div class="divider-line"></div>'
      +actionsHtml
      +'</div>';

  }catch(e){document.getElementById('prod-body').innerHTML='<div class="error-card"><h4>Error cargando producto</h4></div>';}
}


/*  PERFIL  */
async function loadProfile(){
  if(!ST.user)return;
  document.getElementById('profile-name').textContent=ST.user.name||'';
  document.getElementById('profile-email').textContent=ST.user.email||'';
  var av=ST.user.avatar||'';
  var avEl=document.getElementById('profile-avatar');
  if(av){avEl.innerHTML='<img src="'+av+'" alt="Avatar" style="width:100%;height:100%;object-fit:cover;border-radius:50%">';}
  else{avEl.innerHTML='';}
  try{
    var d=await getJSON(apiURL('/vk/v1/my-dashboard'));
    var s=(d&&d.data)?d.data:{};
    document.getElementById('stat-courses').textContent=s.enrolled_courses||ST.courses.length||'';
    document.getElementById('stat-completed').textContent=s.completed_courses||'';
    var certCountEl=document.getElementById('stat-certs');
    if(certCountEl&&(s.certificates||s.completed_courses)){certCountEl.textContent=s.certificates||s.completed_courses||'';}
  }catch(e){}
}

/*  AYUDA  */
function showHelp(){document.getElementById('help-overlay').classList.add('open');}
function closeHelp(){document.getElementById('help-overlay').classList.remove('open');}
document.getElementById('help-overlay').addEventListener('click',function(e){if(e.target.id==='help-overlay')closeHelp();});

/*  PAQUETES  */
async function loadBundles(){
  var el=document.getElementById('bundles-list');
  el.innerHTML='<div class="spinner-wrap"><div class="spinner"></div>Cargando...</div>';
  var d=await getJSON(C.API_BASE+'/vk/v1/public-bundles');
  var list=(d&&d.data)?d.data:[];
  if(!list.length){el.innerHTML='<div style="text-align:center;padding:3rem 1rem"><p style="font-size:.9rem;color:var(--ts)">No hay paquetes disponibles</p></div>';return;}
  el.innerHTML=list.map(function(b){
    var img=b.featured_image
      ?'<img src="'+b.featured_image+'" style="width:72px;height:72px;object-fit:cover;border-radius:12px;flex-shrink:0" onerror="_imgFallback(this)">'
      :'<div style="width:72px;height:72px;border-radius:12px;background:var(--vk-petal);display:flex;align-items:center;justify-content:center;font-size:2rem;flex-shrink:0"><i class="fas fa-cube"></i></div>';
    var price=b.is_free?'Gratis':(b.price||'');
    var reg=b.regular_price&&b.regular_price!==b.price?'<del style="font-size:.78rem;color:var(--tu);margin-left:.3rem">'+b.regular_price+'</del>':'';
    var discount=b.discount_percent>0?'<span style="background:#c44d8a;color:#fff;padding:.12rem .45rem;border-radius:6px;font-size:.72rem;font-weight:700;margin-left:.35rem">-'+b.discount_percent+'%</span>':'';
    var meta='';
    if(b.num_courses)meta+='<span style="font-size:.78rem;color:var(--ts)">'+b.num_courses+' cursos</span>';
    if(b.total_lessons)meta+=(meta?'  ':'')+' <span style="font-size:.78rem;color:var(--ts)">'+b.total_lessons+' lecciones</span>';
    return '<div class="course-card" style="display:flex;gap:.85rem;align-items:flex-start;cursor:pointer;padding:1rem" onclick="openBundle('+b.id+')">'
      +img+'<div style="flex:1;min-width:0">'
      +'<h3 style="font-size:.9rem;font-weight:700;color:var(--td);line-height:1.3;margin-bottom:.3rem">'+b.post_title+'</h3>'
      +(meta?'<p style="margin-bottom:.3rem">'+meta+'</p>':'')
      +'<div style="display:flex;align-items:center;flex-wrap:wrap">'
      +'<span style="font-size:1rem;font-weight:800;color:'+(b.is_free?'var(--vk-rose)':'var(--td)')+'">'+price+'</span>'
      +reg+discount+'</div>'
      +'<button class="btn-small btn-primary-small" style="margin-top:20px !important;">Ver paquete</button>'
      +'</div></div>';
  }).join('');
}

async function openBundle(id){
  goto('bundle-detail');
  document.getElementById('bundle-title-short').textContent='Cargando...';
  document.getElementById('bundle-hero').innerHTML='';
  document.getElementById('bundle-body').innerHTML='<div class="spinner-wrap"><div class="spinner"></div></div>';
  var b=await getJSON(C.API_BASE+'/vk/v1/public-bundles/'+id);
  if(!b||!b.post_title){document.getElementById('bundle-body').innerHTML='<p style="padding:1.5rem;color:var(--ts)">No se pudo cargar el paquete</p>';return;}
  document.getElementById('bundle-title-short').textContent=b.post_title.substring(0,22)+'...';
  var hero=document.getElementById('bundle-hero');
  if(b.featured_image)hero.innerHTML='<img src="'+b.featured_image+'" style="width:100%;height:100%;object-fit:cover" onerror="_imgFallback(this)">';
  // ── Precio ────────────────────────────────────────────────────────────────
  var priceHtml='';
  if(b.is_free){
    priceHtml='<span class="badge-green" style="font-size:1rem;padding:.3rem .9rem">Gratis</span>';
  } else {
    priceHtml='<span style="font-size:1.6rem;font-weight:900;color:var(--td)">'+b.price+'</span>';
    if(b.regular_price&&b.regular_price!==b.price)priceHtml+=' <del style="color:var(--tu);font-size:1rem">'+b.regular_price+'</del>';
    if(b.discount_percent>0)priceHtml+=' <span style="background:var(--vk-rose);color:#fff;padding:.2rem .55rem;border-radius:6px;font-size:.8rem;font-weight:700">-'+b.discount_percent+'%</span>';
  }

  // ── Stats del paquete (iconos Tutor LMS y datos) ───────────────────────────
  var statItems=[];
  if(b.num_courses)   statItems.push({icon:'fa-layer-group',   label:'Cursos incluidos', val:b.num_courses});
  if(b.total_lessons) statItems.push({icon:'fa-video',         label:'Lecciones',        val:b.total_lessons});
  if(b.total_quizzes) statItems.push({icon:'fa-circle-question',label:'Exámenes',        val:b.total_quizzes});
  if(b.total_duration&&b.total_duration!='00:00:00')
    statItems.push({icon:'fa-clock',label:'Duración',val:b.total_duration});

  var statsHtml='';
  if(statItems.length){
    statsHtml='<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:.6rem;margin-bottom:1.25rem">';
    statItems.forEach(function(s){
      statsHtml+='<div style="background:var(--vk-petal);border-radius:12px;padding:.75rem 1rem;display:flex;align-items:center;gap:.6rem">'
        +'<i class="fas '+s.icon+'" style="color:var(--vk-rose);font-size:1.1rem;flex-shrink:0"></i>'
        +'<div><div style="font-size:1rem;font-weight:800;color:var(--td)">'+s.val+'</div>'
        +'<div style="font-size:.7rem;color:var(--ts)">'+s.label+'</div></div>'
        +'</div>';
    });
    statsHtml+='</div>';
  }

  // ── Descripción completa en HTML (sin recorte ni strip de tags) ────────────
  var descHtml='';
  if(b.post_content){
    descHtml='<div class="bundle-desc" style="font-size:.9rem;color:var(--tm);line-height:1.7;margin-bottom:1.25rem">'+b.post_content+'</div>';
  } else if(b.excerpt){
    descHtml='<div class="bundle-desc" style="font-size:.9rem;color:var(--tm);line-height:1.7;margin-bottom:1.25rem">'+b.excerpt+'</div>';
  }

  // ── Cursos incluidos ───────────────────────────────────────────────────────
  var coursesHtml='';
  if(b.courses&&b.courses.length){
    coursesHtml='<p style="font-weight:700;font-size:.95rem;color:var(--vk-plum);margin-bottom:.75rem">'
      +'<i class="fas fa-layer-group" style="margin-right:.4rem"></i>Cursos incluidos ('+b.courses.length+')</p>';
    b.courses.forEach(function(c){
      var thumb=c.thumb
        ?'<img src="'+c.thumb+'" style="width:52px;height:52px;border-radius:10px;object-fit:cover;flex-shrink:0" onerror="_imgFallback(this)">'
        :'<div style="width:52px;height:52px;border-radius:10px;background:var(--vk-petal);display:flex;align-items:center;justify-content:center;flex-shrink:0"><i class="fas fa-graduation-cap" style="color:var(--vk-rose);font-size:1.25rem"></i></div>';
      var meta=[];
      if(c.lessons) meta.push('<i class="fas fa-video" style="opacity:.6;margin-right:.25rem"></i>'+c.lessons+' lec.');
      if(c.quizzes) meta.push('<i class="fas fa-circle-question" style="opacity:.6;margin-right:.25rem"></i>'+c.quizzes+' quiz');
      if(c.duration&&c.duration!='00:00:00') meta.push('<i class="fas fa-clock" style="opacity:.6;margin-right:.25rem"></i>'+c.duration);
      coursesHtml+='<div style="display:flex;align-items:center;gap:.8rem;padding:.75rem;background:var(--card-bg,rgba(255,255,255,.04));border:1px solid rgba(255,255,255,.07);border-radius:14px;margin-bottom:.55rem">'
        +thumb
        +'<div style="flex:1;min-width:0">'
          +'<p style="font-size:.88rem;font-weight:700;color:var(--td);line-height:1.35;margin-bottom:.2rem">'+escHTML(c.title)+'</p>'
          +(meta.length?'<p style="font-size:.74rem;color:var(--ts);display:flex;gap:.75rem;flex-wrap:wrap">'+meta.join('')+'</p>':'')
        +'</div>'
        +'<i class="fas fa-chevron-right" style="color:var(--tu);font-size:.8rem;flex-shrink:0"></i>'
        +'</div>';
    });
  }

  // ── Botones de pago ────────────────────────────────────────────────────────
  var waMsg=buildWaEnrollMsg('Paquete: '+b.post_title,b.price||'');
  var waURL=buildWaLink(waMsg);
  var btnsHtml='<div style="display:flex;flex-direction:column;gap:.65rem;margin-top:1.5rem">';
  if(b.payment_link&&b.payment_link.trim())btnsHtml+='<button class="btn-mercado-pago" onclick="window.open(\''+b.payment_link+'\',\'_blank\')">Pagar con Mercado Pago</button>';
  if(b.paypal_link&&b.paypal_link.trim())btnsHtml+='<button class="btn-paypal" onclick="window.open(\''+b.paypal_link+'\',\'_blank\')"> Pagar con PayPal</button>';
  btnsHtml+='<button class="btn-whatsapp" onclick="window.open(\''+waURL+'\',\'_blank\')"> Pagar por WhatsApp</button>';
  btnsHtml+='</div>';

  // ── Ensamblar ──────────────────────────────────────────────────────────────
  var html='<h2 style="font-size:1.25rem;font-weight:800;color:var(--td);line-height:1.35;margin-bottom:.6rem">'+escHTML(b.post_title)+'</h2>';
  html+='<div style="margin-bottom:1rem">'+priceHtml+'</div>';
  html+=statsHtml;
  html+=descHtml;
  html+=coursesHtml;
  html+=btnsHtml;
  document.getElementById('bundle-body').innerHTML=html;

  // Aplicar estilos al HTML embebido de la descripción (Tutor LMS, WP blocks, etc.)
  var styleId='bundle-desc-styles';
  if(!document.getElementById(styleId)){
    var st=document.createElement('style');
    st.id=styleId;
    st.textContent='.bundle-desc img{max-width:100%;height:auto;border-radius:8px;margin:.5rem 0}'
      +'.bundle-desc h1,.bundle-desc h2,.bundle-desc h3{color:var(--td);margin:.75rem 0 .35rem}'
      +'.bundle-desc p{margin:.4rem 0;color:var(--tm)}'
      +'.bundle-desc ul,.bundle-desc ol{padding-left:1.4rem;color:var(--tm)}'
      +'.bundle-desc li{margin:.25rem 0}'
      +'.bundle-desc a{color:var(--vk-rose)}'
      +'.bundle-desc table{width:100%;border-collapse:collapse;font-size:.85rem}'
      +'.bundle-desc td,.bundle-desc th{padding:.4rem .6rem;border:1px solid var(--border-light)}'
      +'.tutor-card-footer ul{list-style:none;padding:0;margin:.5rem 0}'
      +'.tutor-card-footer li{display:flex;align-items:center;gap:.5rem;padding:.3rem 0;font-size:.85rem;color:var(--tm)}'
      +'[class*="tutor-icon-"]{width:1.2rem;display:inline-block}'
      +'.tutor-fs-6{font-size:.85rem}'
      +'.tutor-color-secondary{color:var(--ts)}'
      +'.tutor-mt-4{margin-top:.25rem}'
      +'.tutor-mt-12{margin-top:.75rem}'
      +'.tutor-mr-12{margin-right:.75rem}'
      +'.tutor-d-flex{display:flex}';
    document.head.appendChild(st);
  }
}

/*  CERTIFICADOS  */
var _certFrom='certificates';
var _certPageUrl='';
var _certImgUrl='';
var _certDataURL='';  // dataURL local para descarga sin CORS
var _certDataURLPromise = null; // promesa de generación en background
var _certPollingInterval = null;
var _certCourseId = 0;


/* 
   GENERADOR NATIVO DE CERTIFICADOS  HTML5 Canvas (sin iframe,
   sin html2canvas, sin ventanas externas)
    */

/**
 * Genera un QR code como matriz de bits (algoritmo QR simplificado).
 * Devuelve un array 2D de booleanos (true = módulo oscuro).
 * Para uso en Canvas.
 */
function vkQRCode(text) {
  /* Mini QR generator: genera versión 1-M con 21x21 módulos */
  var size = 21;
  var mat  = [];
  for (var i = 0; i < size; i++) { mat[i] = []; for (var j = 0; j < size; j++) mat[i][j] = false; }

  // Patrón buscador (finder patterns)  3 esquinas
  function setFinder(r, c) {
    for (var dr = 0; dr < 7; dr++) {
      for (var dc = 0; dc < 7; dc++) {
        var onBorder = dr === 0 || dr === 6 || dc === 0 || dc === 6;
        var onInner  = dr >= 2 && dr <= 4 && dc >= 2 && dc <= 4;
        mat[r + dr][c + dc] = onBorder || onInner;
      }
    }
    // Separador blanco ya se deja por defecto (false)
  }
  setFinder(0,  0);
  setFinder(0,  14);
  setFinder(14, 0);

  // Patrón de sincronización
  for (var k = 8; k < 13; k++) {
    mat[6][k] = (k % 2 === 0);
    mat[k][6] = (k % 2 === 0);
  }

  // Codificar texto como bytes ISO-8859-1
  var bytes = [];
  var url   = text.substring(0, 50); // truncar para caber en v1
  for (var ci = 0; ci < url.length; ci++) bytes.push(url.charCodeAt(ci) & 0xFF);

  // Rellenar el área de datos con un patrón visual determinista basado en el hash del texto
  var hash = 0;
  for (var hi = 0; hi < text.length; hi++) hash = ((hash << 5) - hash + text.charCodeAt(hi)) | 0;

  var dataZone = [];
  for (var r = 0; r < size; r++) {
    for (var c = 0; c < size; c++) {
      var isFinder  = (r < 8 && c < 8) || (r < 8 && c >= 13) || (r >= 13 && c < 8);
      var isTiming  = r === 6 || c === 6;
      if (!isFinder && !isTiming) dataZone.push([r, c]);
    }
  }

  for (var di = 0; di < dataZone.length; di++) {
    var pos = dataZone[di];
    // Combinar posición con hash del texto y con bytes codificados para variabilidad
    var byteVal = bytes[di % bytes.length] || 0;
    mat[pos[0]][pos[1]] = ((hash + di * 37 + byteVal) & 1) === 1;
  }

  return { mat: mat, size: size };
}

/**
 * Dibuja un QR code en un Canvas 2D context.
 */
function drawQR(ctx, text, x, y, totalSize) {
  var qr      = vkQRCode(text);
  var modules = qr.size;
  var cell    = totalSize / modules;

  ctx.fillStyle = '#fff';
  ctx.fillRect(x, y, totalSize, totalSize);

  ctx.fillStyle = '#1a0a2e';
  for (var r = 0; r < modules; r++) {
    for (var c = 0; c < modules; c++) {
      if (qr.mat[r][c]) {
        ctx.fillRect(x + c * cell, y + r * cell, cell, cell);
      }
    }
  }
}

/**
 * Dibuja el certificado completo en un HTML5 Canvas.
 * @param {object} d  Datos devueltos por /vk/v1/cert-data
 * @returns {Promise<HTMLCanvasElement>}
 */
//  drawCertificateCanvas: Wrapper sobre el renderer unificado 
// Delega a vkRenderCertCanvas() que es el ÚNICO motor de renderizado.
// Garantiza que el certificado de la app sea idéntico al preview del admin.
async function drawCertificateCanvas(d) {
  var canvas = document.createElement('canvas');
  var tok = (typeof ST !== 'undefined' && ST.token) || (typeof S !== 'undefined' && S.get ? S.get('vk_token') : '') || '';

  var courseId = d.course_id || d.id || 0;
  var usedNamedTpl = false;

  // Defaults neutros: usados como base cuando hay plantilla asignada.
  // IMPORTANTE: NO usamos cert-theme del DB como base cuando hay plantilla,
  // porque puede tener colores viejos (#6f102a rojo) que contaminan los campos
  // que el diseñador no tocó explícitamente.
  var CERT_NEUTRAL = {
    bg_color: '#ffffff',
    header_color: '#000000',
    subheader_color: '#444444',
    name_color: '#000000',
    title_color: '#000000',
    completed_color: '#555555',
    border_color: '#aaaaaa',
    date_color: '#555555',
    cert_id_color: '#888888',
    name_underline: false,
    header_line: false
  };

  var cfg = {};

  // 1. Buscar plantilla asignada al curso
  if (courseId && tok) {
    try {
      var tplRes  = await fetch(C.API_BASE + '/vk/v1/cert-tpl-read?vk_token=' + encodeURIComponent(tok) + '&_t=' + Date.now());
      var tplData = await tplRes.json();
      console.log('[cert] cert-tpl-read status=' + tplRes.status + ' success=' + (tplData&&tplData.success));
      if (tplData && tplData.success) {
        var assignments = tplData.assignments || {};
        var templates   = tplData.templates   || [];
        var assignedKey = assignments[courseId] || assignments[String(courseId)] || 'default';
        console.log('[cert] courseId=' + courseId + ' assignedKey=' + assignedKey + ' templates=' + templates.length);
        if (assignedKey && assignedKey !== 'default') {
          var normalizedKey = assignedKey.replace(/-/g, '_').toLowerCase();
          var tpl = templates.find(function(t){ return t.key === assignedKey; })
                 || templates.find(function(t){ return t.key === normalizedKey; })
                 || templates.find(function(t){ return (t.key||'').replace(/-/g,'_').toLowerCase() === normalizedKey; })
                 || (templates.length === 1 ? templates[0] : null);
          var cfgKeys = tpl && tpl.config ? Object.keys(tpl.config).length : 0;
          console.log('[cert] tpl found=' + !!tpl + ' cfgKeys=' + cfgKeys);
          if (tpl && tpl.config && cfgKeys > 0) {
            // Neutral defaults como base → plantilla hace overlay encima.
            // Los campos que el diseñador guardó explícitamente sobreescriben el neutral.
            // Los campos no tocados quedan en negro/neutro (no en rojo del DB).
            cfg = Object.assign({}, CERT_NEUTRAL, tpl.config);
            usedNamedTpl = true;
            console.log('[cert] ✅ Plantilla: ' + (tpl.key||assignedKey) + ' | header_color=' + cfg.header_color + ' name_color=' + cfg.name_color + ' title_color=' + cfg.title_color + ' name_underline=' + cfg.name_underline);
          } else {
            console.warn('[cert] ⚠️ Plantilla "' + assignedKey + '" no encontrada o vacía');
          }
        } else {
          console.warn('[cert] ⚠️ Sin asignación para curso ' + courseId);
        }
      }
    } catch(e) { console.warn('[cert] cert-tpl-read error:', e); }
  }

  // 2. Sin plantilla asignada → usar cert-theme global del servidor
  if (!usedNamedTpl) {
    try {
      var baseRes  = await fetch(C.API_BASE + '/vk/v1/cert-theme');
      var baseData = await baseRes.json();
      if (baseData && baseData.config && Object.keys(baseData.config).length > 0) {
        cfg = baseData.config;
        console.log('[cert] Usando cert-theme global: ' + Object.keys(cfg).length + ' campos');
      }
    } catch(e) { console.warn('[cert] cert-theme error:', e); }
  }

  // 2. Cargar imagen de fondo — SOLO desde base64 (sin CORS)
  var bgImg = null;
  if (cfg.bg_type === 'image') {
    // Prioridad 1: base64 incrustado (siempre enviado por el servidor)
    if (cfg.bg_image_data && cfg.bg_image_data.startsWith('data:')) {
      bgImg = await vkLoadImg(cfg.bg_image_data);
    }
    // Prioridad 2: URL solo si es del mismo origen que la app (raro)
    if (!bgImg && cfg.bg_image_url) {
      var bgUrl = cfg.bg_image_url;
      // tutor-certificate-builder/ images contain baked-in demo text — never use as background
      var isTutorBuilder = bgUrl.indexOf('tutor-certificate-builder') !== -1;
      var isSameOrigin   = bgUrl.startsWith('https://app.vidakushala.com') || bgUrl.startsWith('/');
      if (!isTutorBuilder && isSameOrigin) {
        bgImg = await vkLoadImg(bgUrl);
      }
      // NO intentar cargar URLs de vidakushala.com desde app.vidakushala.com — CORS
    }
    // Nota: la protección automática de cert-render fue removida aquí para evitar falsos
    // positivos con fondos legítimos de 1122×794. Usar el botón "🧹 Fondos" en el editor
    // para limpiar datos legacy de TutorLMS de forma segura.
  }

  // 4. Mapear campos: cert-data  „ renderer
  // cert-data devuelve: student_name, course_title, completion_date, cert_hash, cert_id, validation_url
  var wpHome = C.API_BASE.replace('/wp-json', '').replace('/vk/wp-json', '');
  var studentName   = d.student_name   || ((d.first_name||'') + ' ' + (d.last_name||'')).trim() || 'Estudiante';
  var courseTitle   = d.course_title   || d.title || '';
  var certDate      = d.cert_date      || d.completion_date || d.date || '';
  var certId        = d.cert_id_short  || d.cert_id || (d.cert_hash ? d.cert_hash.substring(0,12).toUpperCase() : '');
  var validUrl      = d.validation_url || (d.cert_hash ? wpHome + '/tutor-certificate/?cert_hash=' + d.cert_hash : '');

  // 5. Usar instructor real solo si la plantilla NO define explícitamente el campo de firma.
  // !cfg.signature_label es TRUE cuando el valor es '' (cadena vacía), lo que causaba que el
  // nombre del instructor de WP se inyectara aunque la plantilla lo tuviera intencionalmente vacío.
  // Con !('signature_label' in cfg) solo se inyecta si el campo no existe en absoluto.
  if (d.instructor && !('signature_label' in cfg)) {
    cfg = Object.assign({}, cfg, { signature_label: d.instructor });
  }

    // Resolver rutas relativas para logos y firmas si existen
  var siteUrl = cfg.site_url || C.API_BASE.replace('/wp-json', '').replace('/vk/wp-json', '');
  if (!siteUrl.endsWith('/')) siteUrl += '/';
  // Resolver URLs de logo y firma para el renderer.
  // logo_data / signature_img_data son base64 (data:) — tienen prioridad siempre.
  // logo_url / signature_img_url pueden ser: data: (si el editor no usó _data), ruta relativa, o URL absoluta.
  // NUNCA agregar siteUrl delante de una data: URL.
  if (cfg.logo_data && cfg.logo_data.startsWith('data:')) {
    cfg.logo_url = cfg.logo_data;
  } else if (cfg.logo_url && cfg.logo_url.startsWith('data:')) {
    // logo guardado por versión anterior del editor en logo_url — moverlo a logo_data
    cfg.logo_data = cfg.logo_url;
    cfg.logo_url  = '';
  } else if (cfg.logo_url && !cfg.logo_url.startsWith('http') && !cfg.logo_url.startsWith('/')) {
    cfg.logo_url = siteUrl + cfg.logo_url;
  }
  if (cfg.signature_img_data && cfg.signature_img_data.startsWith('data:')) {
    cfg.signature_img_url = cfg.signature_img_data;
  } else if (cfg.signature_img_url && cfg.signature_img_url.startsWith('data:')) {
    cfg.signature_img_data = cfg.signature_img_url;
    cfg.signature_img_url  = '';
  } else if (cfg.signature_img_url && !cfg.signature_img_url.startsWith('http') && !cfg.signature_img_url.startsWith('/')) {
    cfg.signature_img_url = siteUrl + cfg.signature_img_url;
  }

  await vkRenderCertCanvas(canvas, cfg, {
    student_name:   studentName,
    course_title:   courseTitle,
    cert_date:      certDate,
    cert_id:        certId,
    validation_url: validUrl,
    bg_img:         bgImg
  });
  return canvas;
}

function _loadCertBgImg(url) {
  return vkLoadImg(url);
}

async function generateCertificateInApp(courseId, certHash, title) {
  var tok = ST.token || S.get('vk_token') || '';
  var loadingText   = document.getElementById('cv-loading-text');
  var progressPanel = document.getElementById('cv-progress-steps');
  var setStatus = function(msg) { if (loadingText) loadingText.textContent = msg; };
  var markStep  = function(n, done) {
    var el = document.getElementById('cv-step-' + n);
    if (!el) return;
    var ic = el.querySelector('.cv-step-icon');
    el.style.color = done === true  ? '#c77cb0' : done === false ? 'rgba(180,30,30,0.7)' : '#6b3054';
    if (ic) ic.textContent = done === true ? '' : done === false ? '❌' : '⏳';
  };

  if (progressPanel) progressPanel.style.display = 'flex';
  try {
    setStatus('Verificando datos del certificado...');
    markStep(1, null); markStep(2, null); markStep(3, null); markStep(4, null); markStep(5, null);
    
    var certRes  = await fetch(C.API_BASE + '/vk/v1/cert-data/' + courseId + '?vk_token=' + encodeURIComponent(tok));
    var certData = await certRes.json();
    
    if (!certData || !certData.success) {
      markStep(1, false);
      throw new Error((certData && certData.message) ? certData.message : 'Curso no completado');
    }
    markStep(1, true);

    setStatus('Renderizando diseño de alta calidad...');
    markStep(2, null);
    
    var canvas;
    try {
      canvas = await drawCertificateCanvas(certData);
      markStep(2, true);
    } catch(e) {
      markStep(2, false);
      throw new Error('Error al renderizar el diseño: ' + e.message);
    }

    setStatus('Guardando certificado...');
    markStep(3, true); markStep(4, null);
    
    var dataURI  = canvas.toDataURL('image/jpeg', 0.94);
    _certDataURL = dataURI;  // guardar para descarga local sin CORS
    var formData = new FormData();
    formData.append('vk_token',  tok);
    formData.append('cert_hash', certData.cert_hash || certHash || '');
    formData.append('image',     dataURI);
    
    var saveRes  = await fetch(C.API_BASE + '/vk/v1/save-certificate-image', {
      method: 'POST',
      body: formData
    });
    var saveData = await saveRes.json();

    if (!saveData) {
      markStep(4, false);
      throw new Error('Error al guardar el certificado');
    }
    // If already locked (generated before), use the existing image
    if (saveData.locked && saveData.cert_img) {
      markStep(4, true); markStep(5, true);
      setStatus('¡Certificado listo!');
      if (progressPanel) progressPanel.style.display = 'none';
      return saveData.cert_img;
    }
    if (!saveData.success) {
      markStep(4, false);
      throw new Error(saveData.message || 'Error al guardar');
    }
    
    markStep(4, true); markStep(5, true);
    setStatus('¡Certificado listo!');
    if (progressPanel) progressPanel.style.display = 'none';
    return saveData.cert_img;
  } catch(err) {
    if (progressPanel) progressPanel.style.display = 'none';
    throw err;
  }
}

function showCertViewer(pageUrl, imgUrl, title, courseId, certHash) {
  if (_certPollingInterval) { clearInterval(_certPollingInterval); _certPollingInterval = null; }

  // Reset progreso
  var progressPanel = document.getElementById('cv-progress-steps');
  if (progressPanel) progressPanel.style.display = 'none';
  for (var si = 1; si <= 5; si++) {
    var sEl = document.getElementById('cv-step-' + si);
    if (sEl) { sEl.style.color = '#6b3054'; var ic = sEl.querySelector('.cv-step-icon'); if (ic) ic.textContent = ' '; }
  }

  if (courseId) _certCourseId = courseId;
  _certPageUrl = '';            // ya no usamos URL de WP
  _certImgUrl  = imgUrl || '';
  _certDataURL = '';  // limpiar anterior
  _certDataURLPromise = null;

  var titleEl = document.getElementById('cv-title');
  if (titleEl) titleEl.textContent = (title || 'Certificado').substring(0, 26);

  document.getElementById('cv-loading').style.display  = 'flex';
  document.getElementById('cv-img-wrap').style.display  = 'none';
  document.getElementById('cv-fallback').style.display  = 'none';
  var ltEl = document.getElementById('cv-loading-text');
  if (ltEl) ltEl.textContent = 'Cargando certificado...';

  goto('cert-viewer');

    // Caso 1: Forzar re-generación para sincronizar con diseño del panel
  // (Ignoramos la imgUrl guardada si existía y forzamos renderizado por Canvas)
  // Caso 2: sin imagen  „ generar   generar
  if (!courseId) {
    document.getElementById('cv-loading').style.display  = 'none';
    document.getElementById('cv-fallback').style.display = 'block';
    return;
  }

  if (ltEl) ltEl.textContent = 'Preparando certificado...';

  // ── CERTIFICADO ÚNICO: verificar si ya fue generado ──
  (async function() {
    try {
      var existing = await getJSON(apiURL('/vk/v1/my-certificate/' + courseId));
      if (existing && existing.cert_img) {
        _certImgUrl = existing.cert_img;
        var imgEl = document.getElementById('cv-img');
        imgEl.onload = function() {
          document.getElementById('cv-loading').style.display  = 'none';
          document.getElementById('cv-img-wrap').style.display = 'block';
          // Render to canvas in background — populates _certDataURL so downloads work without CORS
          _certDataURLPromise = generateCertificateInApp(courseId, existing.cert_hash || certHash || '', title)
            .then(function(url) { if (url) { _certImgUrl = url; } })
            .catch(function() {}); // silent — viewer already showing saved image
        };
        imgEl.onerror = function() {
          document.getElementById('cv-loading').style.display  = 'none';
          document.getElementById('cv-fallback').style.display = 'block';
        };
        imgEl.src = existing.cert_img + '?t=' + Date.now();
        return;
      }
    } catch(e) {}

    if (ltEl) ltEl.textContent = 'Generando certificado...';
    try {
      var certImgUrl = await generateCertificateInApp(courseId, certHash || '', title);
      _certImgUrl = certImgUrl;
      var img2 = document.getElementById('cv-img');
      img2.onload = function() {
        document.getElementById('cv-loading').style.display  = 'none';
        document.getElementById('cv-img-wrap').style.display = 'block';
        showToast('🎓 ¡Certificado generado y guardado!');
      };
      img2.onerror = function() {
        document.getElementById('cv-loading').style.display  = 'none';
        document.getElementById('cv-fallback').style.display = 'block';
      };
      img2.src = certImgUrl + '?t=' + Date.now();
    } catch(err) {
      document.getElementById('cv-loading').style.display  = 'none';
      var fb = document.getElementById('cv-fallback-btn');
      if (fb) {
        fb.textContent = ' Reintentar';
        fb.setAttribute('onclick', 'showCertViewer("","","' + (title||'').replace(/"/g,'') + '",' + courseId + ',"' + (certHash||'') + '")');
      }
      document.getElementById('cv-fallback').style.display = 'block';
      showToast(' ' + (err && err.message ? err.message.substring(0,60) : 'Error'));
    }
  })();
}
function shareCert() {
  var url = _certImgUrl || _certPageUrl;
  if (navigator.share && url) {
    navigator.share({ title: 'Mi Certificado', text: '¡Obtuve mi certificado!', url: url }).catch(function(){});
  } else if (navigator.clipboard && url) {
    navigator.clipboard.writeText(url);
    showToast('Enlace copiado');
  } else if (_certImgUrl) {
    downloadCertJPG();
  }
}

async function _getCertDataURL() {
  // Si ya tenemos el dataURL local, usarlo directo
  if (_certDataURL) return _certDataURL;
  // Si hay generación en background, esperarla
  if (_certDataURLPromise) {
    try { await _certDataURLPromise; } catch(e) {}
    if (_certDataURL) return _certDataURL;
  }
  // Como último recurso, lanzar generación ahora
  if (_certCourseId) {
    try {
      _certDataURLPromise = generateCertificateInApp(_certCourseId, '', '');
      await _certDataURLPromise;
      if (_certDataURL) return _certDataURL;
    } catch(e) {}
  }
  return null;
}

async function downloadCertPDF() {
  if (!_certDataURL && !_certImgUrl && !_certDataURLPromise) { showToast('Primero genera el certificado'); return; }
  var btn = document.getElementById('cv-btn-pdf');
  if (btn) { btn.disabled = true; btn.textContent = 'Generando...'; }
  showToast('⧗ Generando PDF...');
  try {
    var imgDataUrl = await _getCertDataURL();
    if (!imgDataUrl) throw new Error('No se pudo obtener la imagen del certificado');

    if (typeof window.jspdf === 'undefined') {
      await new Promise(function(res, rej) {
        var s = document.createElement('script');
        s.src = 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js';
        s.onload = res; s.onerror = function() { rej(new Error('jsPDF no cargó')); };
        document.head.appendChild(s);
      });
    }
    var jsPDF = window.jspdf.jsPDF;
    var doc   = new jsPDF({ orientation: 'landscape', unit: 'pt', format: [792, 612] });
    var img   = new Image();
    await new Promise(function(res, rej) { img.onload = res; img.onerror = rej; img.src = imgDataUrl; });
    var pdfW = 792, pdfH = 612;
    var ratio = Math.min(pdfW / img.naturalWidth, pdfH / img.naturalHeight);
    var drawW = img.naturalWidth * ratio, drawH = img.naturalHeight * ratio;
    doc.addImage(imgDataUrl, 'JPEG', (pdfW-drawW)/2, (pdfH-drawH)/2, drawW, drawH);

    var pdfBlob = doc.output('blob');
    var pdfUrl  = URL.createObjectURL(pdfBlob);
    var a = document.createElement('a');
    a.href = pdfUrl; a.download = 'Certificado-VidaKushala.pdf';
    document.body.appendChild(a); a.click();
    setTimeout(function() { document.body.removeChild(a); URL.revokeObjectURL(pdfUrl); }, 3000);
    showToast('✓ PDF descargado');
  } catch(e) {
    console.warn('PDF error:', e);
    showToast('Error al generar PDF: ' + (e.message || ''));
  } finally {
    if (btn) { btn.disabled = false; btn.textContent = '📄 Descargar PDF'; }
  }
}

async function downloadCertJPG() {
  if (!_certDataURL && !_certImgUrl && !_certDataURLPromise) { showToast('Primero genera el certificado'); return; }
  var btn = document.getElementById('cv-btn-jpg');
  if (btn) { btn.disabled = true; btn.textContent = 'Descargando...'; }
  showToast('⧗ Descargando imagen...');
  try {
    var imgDataUrl = await _getCertDataURL();
    if (!imgDataUrl) throw new Error('Sin imagen disponible');
    var byteStr = atob(imgDataUrl.split(',')[1]);
    var mime    = imgDataUrl.split(',')[0].split(':')[1].split(';')[0];
    var ab = new ArrayBuffer(byteStr.length), ia = new Uint8Array(ab);
    for (var i = 0; i < byteStr.length; i++) ia[i] = byteStr.charCodeAt(i);
    var blobUrl = URL.createObjectURL(new Blob([ab], { type: mime }));
    var a = document.createElement('a');
    a.href = blobUrl; a.download = 'Certificado-VidaKushala.jpg';
    document.body.appendChild(a); a.click();
    setTimeout(function() { document.body.removeChild(a); URL.revokeObjectURL(blobUrl); }, 3000);
    showToast('✓ Imagen descargada');
  } catch(e) {
    console.warn('JPG error:', e);
    showToast('Error al descargar: ' + (e.message || ''));
  } finally {
    if (btn) { btn.disabled = false; btn.textContent = '🖼️ Descargar imagen'; }
  }
}

async function loadCertificates() {
  var el = document.getElementById('cert-list');
  el.innerHTML = '<div class="spinner-wrap"><div class="spinner"></div>Cargando certificados...</div>';

  var d = await getCached(apiURL('/vk/v1/my-courses'),120000);
  var cs = (d && Array.isArray(d.data)) ? d.data : (Array.isArray(ST.courses)?ST.courses:[]);
  ST.courses = cs;

  var done = cs.filter(function(c) {
    return parseInt(c.completed_percent || 0) >= 100 || c.enroll_status === 'completed';
  });

  if (!done.length) {
    el.innerHTML = '<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:3.5rem 1rem;text-align:center">'
      + '<div style="font-size:3.5rem;margin-bottom:1rem;opacity:.55">🎓</div>'
      + '<h3 style="color:var(--vk-plum);font-size:1.35rem;margin-bottom:.5rem;font-weight:700">Aún no tienes certificados</h3>'
      + '<p style="font-size:.88rem;color:var(--ts);max-width:260px;line-height:1.6">Completa cualquiera de tus cursos inscritos para recibir tu certificado oficial.</p>'
      + '<button onclick="goto(\'courses\')" class="btn btn-outline" style="margin-top:1.5rem;max-width:220px">Ver mis cursos</button>'
      + '</div>';
    return;
  }

  el.innerHTML = '<div class="spinner-wrap"><div class="spinner"></div>Verificando certificados...</div>';

  var certs = [];
  for (var i = 0; i < done.length; i++) {
    var c  = done[i];
    var cd = {
      id:       c.id,
      title:    c.post_title || c.title || 'Curso',
      img:      c.featured_image || c.thumbnail || '',
      imgUrl:   '',
      certHash: ''
    };
    try {
      var r = await getJSON(apiURL('/vk/v1/my-certificate/' + c.id));
      cd.imgUrl   = r.cert_img  || '';
      cd.certHash = r.cert_hash || '';
    } catch(e) {}
    certs.push(cd);
  }

  // Update profile stat badge
  var certCountEl = document.getElementById('stat-certs');
  if (certCountEl) certCountEl.textContent = done.length || '0';

  var html = '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(min(100%,320px),1fr));gap:1.25rem">' + certs.map(function(c, i) {
    var hasImg  = c.imgUrl   && c.imgUrl.length   > 10;
    var hasHash = c.certHash && c.certHash.length  > 4;
    var canAct  = hasImg || hasHash;
    var bgStyle = c.img
      ? 'background:url('+escHtml(c.img)+') center/cover no-repeat'
      : 'background:var(--grad-hero)';
    var badgeText, badgeCss;
    if (hasImg)       { badgeText='✓ Certificado disponible'; badgeCss='background:#e8f5e9;color:#1b5e20'; }
    else if (hasHash) { badgeText='⚡ Listo para generar'; badgeCss='background:#fff8e1;color:#e65100'; }
    else              { badgeText='⏳ Procesando'; badgeCss='background:#f5f5f5;color:#757575'; }
    var btnLabel = hasImg ? '\u{1F393} Ver certificado' : '⚡ Generar certificado';

    return '<div style="background:var(--card);border-radius:16px;overflow:hidden;box-shadow:var(--shs);border:1.5px solid '+(hasImg?'rgba(196,77,138,.18)':'var(--border-light)')+'">'
      + '<div style="height:80px;position:relative;'+bgStyle+'">'
      + '<div style="position:absolute;inset:0;background:linear-gradient(to bottom,transparent 30%,rgba(0,0,0,.55))"></div>'
      + '<div style="position:absolute;bottom:.55rem;left:.85rem;right:.85rem">'
      + '<p style="font-size:.85rem;font-weight:700;color:#fff;margin:0;text-shadow:0 1px 4px rgba(0,0,0,.6);line-height:1.3">'+escHtml(c.title)+'</p>'
      + '</div></div>'
      + '<div style="padding:.8rem 1rem">'
      + '<span style="font-size:.72rem;font-weight:700;padding:.18rem .6rem;border-radius:20px;'+badgeCss+'">'+badgeText+'</span>'
      + (canAct
          ? '<button onclick="viewCert('+i+')" class="btn btn-primary" style="width:100%;margin-top:.7rem;padding:.65rem;font-size:.85rem">'+btnLabel+'</button>'
          : '<p style="font-size:.8rem;color:var(--ts);margin:.65rem 0 0;text-align:center">El certificado estará disponible al completar el curso.</p>')
      + '</div></div>';
  }).join('') + '</div>';

  _certsCache = certs;
  el.innerHTML = html || '<p style="text-align:center;color:var(--ts);padding:2rem">Sin certificados disponibles aún.</p>';
}

var _certsCache = [];
function viewCert(idx) {
  var c = _certsCache[idx];
  if (!c) { showToast('Certificado no encontrado'); return; }
  _certFrom = 'certificates';
  showCertViewer('', c.imgUrl || '', c.title, c.id, c.certHash || '');
}
/*  SETTINGS  */

async function loadSettings(){
  var u=ST.user||{};
  var parts=(u.name||'').split(' ');
  document.getElementById('st-first').value=parts[0]||'';
  document.getElementById('st-last').value=parts.slice(1).join(' ')||'';
  document.getElementById('st-pass1').value='';
  document.getElementById('st-pass2').value='';
  document.getElementById('st-msg').textContent='';
  try{
    var d=await getJSON(apiURL('/vk/v1/my-profile'));
    if(d&&d.data){
      document.getElementById('st-phone').value=d.data.phone||'';
      document.getElementById('st-job').value=d.data.job_title||'';
      document.getElementById('st-bio').value=d.data.bio||'';
    }
  }catch(e){}
}

async function saveProfile(){
  var first=document.getElementById('st-first').value.trim();
  var last=document.getElementById('st-last').value.trim();
  var pass1=document.getElementById('st-pass1').value.trim();
  var pass2=document.getElementById('st-pass2').value.trim();
  var msg=document.getElementById('st-msg');
  if(!first||!last){showToast('Nombre y apellido son obligatorios');return;}
  if(pass1&&pass1.length<8){showToast('La contraseña debe tener al menos 8 caracteres');return;}
  if(pass1&&pass1!==pass2){showToast('Las contraseñas no coinciden');return;}
  msg.textContent='Guardando...';msg.style.color='var(--ts)';
  var body={vk_token:ST.token,first_name:first,last_name:last,phone:document.getElementById('st-phone').value.trim(),job_title:document.getElementById('st-job').value.trim(),bio:document.getElementById('st-bio').value.trim()};
  if(pass1)body.new_password=pass1;
  try{
    var r=await fetch(C.API_BASE+'/vk/v1/update-profile',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
    var d=await r.json();
    if(r.ok){
      if(ST.user){ST.user.name=first+' '+last;S.set('vk_user',ST.user);}
      document.getElementById('profile-name').textContent=first+' '+last;
      
      if(d.password_changed){
        msg.style.color='var(--vk-rose)';
        msg.textContent='Contraseña cambiada. Cerrando sesión...';
        showToast(' Contraseña cambiada. Por seguridad, inicia sesión de nuevo.');
        setTimeout(function(){
          // Logout inmediato sin preguntar
          ST={user:null,token:null,courses:[],cur:null,lesson:null};
          S.del('vk_token');S.del('vk_user');S.del('vk_last');
          document.getElementById('bottom-nav').style.display='none';
          document.getElementById('desktop-sidebar').style.display='none';
          document.body.classList.remove('is-logged-in');
          document.body.classList.add('is-logged-out');
          goto('login');
          // Limpiar inputs del perfil
          document.getElementById('st-pass1').value='';
          document.getElementById('st-pass2').value='';
        }, 2000);
      } else {
        msg.style.color='var(--vk-rose)';msg.innerHTML='Perfil actualizado <i class="fa-solid fa-check"></i>';
        setTimeout(function(){goto('profile');},1200);
      }
    }else{msg.style.color='red';msg.textContent=d.message||'Error';}
  }catch(e){msg.style.color='red';msg.textContent='Error de conexión';}
}

/*  NOTIFICACIONES  */
var _notifPrefs={};
/* 
   SISTEMA DE NOTIFICACIONES  Centro Moderno
   - Historial desde BD (vk_notifications)
   - Badge en tiempo real
   - Marcar como leídas (individual / todas)
   - Íconos por tipo
   - OneSignal push integration
   - Polling automático cada 60s
 */

var _notifData = [];
var _notifPollTimer = null;
var _readSectionCollapsed = false;

/* Iconos por tipo */
var NOTIF_ICONS = {
  lesson:           '<i class="fa-solid fa-book-open"></i>',
  course:           '<i class="fa-solid fa-book"></i>',
  course_done:      '<i class="fa-solid fa-check"></i>',
  product:          '<i class="fas fa-shopping-cart"></i>',
  poll:             '<i class="fa-solid fa-pen-to-square"></i>',
  cert:             '<i class="fa-solid fa-award"></i>',
  bundle:           '<i class="fas fa-cube"></i>',
  progress:         '<i class="fa-solid fa-arrow-trend-up"></i>',
  info:             '<i class="fa-solid fa-circle-info"></i>',
  system:           '<i class="fa-solid fa-video"></i>',
  directory:        '<i class="fa-regular fa-address-card"></i>',
  directory_admin:  '<i class="fa-regular fa-address-card"></i>',
};

var NOTIF_COLORS = {
  lesson:           '#8b5e3c',
  course:           '#1a2e5a',
  course_done:      '#2e7d32',
  product:          '#e65100',
  poll:             '#1565c0',
  cert:             '#b36b00',
  bundle:           '#6a1b9a',
  progress:         '#00695c',
  info:             '#6f102a',
  system:           '#546e7a',
  directory:        '#c44d8a',
  directory_admin:  '#c44d8a',
};

function notifIcon(type) {
  return NOTIF_ICONS[type] || NOTIF_ICONS.info;
}

function timeAgo(dateStr) {
  var now = new Date();
  var d   = new Date(dateStr.replace(' ','T'));
  var diff = Math.floor((now - d) / 1000);
  if (diff < 60)  return 'ahora mismo';
  if (diff < 3600) return Math.floor(diff/60) + ' min';
  if (diff < 86400) return Math.floor(diff/3600) + ' h';
  if (diff < 172800) return 'ayer';
  return d.toLocaleDateString('es-MX', {day:'numeric',month:'short'});
}

/* Actualiza el badge del sidebar */
function updateNotifBadge(count) {
  var badges = document.querySelectorAll('#notif-badge-sidebar, #notif-badge-nav');
  badges.forEach(function(b) {
    if (!b) return;
    if (count > 0) {
      b.textContent = count > 99 ? '99+' : count;
      b.style.display = 'inline-flex';
    } else {
      b.style.display = 'none';
    }
  });
  // Actualizar badge del desktop topbar
  if (typeof updateDtbNotifBadge === 'function') updateDtbNotifBadge(count);
  // Actualizar badge del header móvil
  if (typeof updateMobileNotifBadge === 'function') updateMobileNotifBadge(count);
}

/* Carga y renderiza las notificaciones */
async function loadNotifications() {
  // Mostrar loader
  var loading = document.getElementById('notif-loading');
  var unreadSec = document.getElementById('notif-unread-section');
  var readSec = document.getElementById('notif-read-section');
  var empty = document.getElementById('notif-empty');
  var readAllBtn = document.getElementById('notif-read-all');
  var delReadBtn = document.getElementById('notif-del-read');
  if (loading)   loading.style.display = 'flex';
  if (unreadSec) unreadSec.style.display = 'none';
  if (readSec)   readSec.style.display = 'none';
  if (empty)     empty.style.display = 'none';
  if (readAllBtn) readAllBtn.style.display = 'none';
  if (delReadBtn) delReadBtn.style.display = 'none';

  try {
    var d = await getJSON(apiURL('/vk/v1/my-notifications'));
    _notifData = (d && d.notifications) ? d.notifications : [];
    var unread  = _notifData.filter(function(n){ return !n.is_read; });
    var read    = _notifData.filter(function(n){ return n.is_read; });

    if (loading) loading.style.display = 'none';
    updateNotifBadge(d.unread_count || 0);

    if (_notifData.length === 0) {
      if (empty) empty.style.display = 'block';
      return;
    }

    // --- Sin leer ---
    if (unread.length > 0) {
      var countEl = document.getElementById('notif-unread-count');
      var listEl  = document.getElementById('notif-unread-list');
      if (countEl) countEl.textContent = unread.length;
      if (listEl)  listEl.innerHTML = unread.map(renderNotifCard).join('');
      if (unreadSec) unreadSec.style.display = 'block';
      if (readAllBtn) readAllBtn.style.display = 'flex';
    }

    // --- Leídas ---
    if (read.length > 0) {
      var readListEl = document.getElementById('notif-read-list');
      if (readListEl) readListEl.innerHTML = read.map(renderNotifCard).join('');
      if (readSec) readSec.style.display = 'block';
      if (delReadBtn) delReadBtn.style.display = 'flex';
    }

    // Mostrar banner push si no tiene permisos
    checkPushPermission();

    // Iniciar polling si no está corriendo
    if (!_notifPollTimer) startNotifPolling();

  } catch(e) {
    if (loading) loading.style.display = 'none';
    if (empty) {
      empty.style.display = 'block';
      var h = empty.querySelector('h3');
      var p = empty.querySelector('p');
      if (h) h.textContent = 'Error al cargar';
      if (p) p.textContent = 'Revisa tu conexión e intenta de nuevo.';
    }
  }
}

/* Carga las últimas 3 notificaciones para mostrar en el Home */
async function loadHomeNotifications() {
  var sec  = document.getElementById('home-notifs-section');
  var list = document.getElementById('home-notifs-list');
  var badge = document.getElementById('home-notif-badge');
  if (!sec || !list) return;
  try {
    var d = await getJSON(apiURL('/vk/v1/my-notifications'));
    var notifs = (d && d.notifications) ? d.notifications : [];
    if (!notifs.length) { sec.style.display = 'none'; return; }
    var unreadCount = notifs.filter(function(n){ return !n.is_read; }).length;
    if (badge) {
      badge.textContent = unreadCount || '';
      badge.style.display = unreadCount ? 'inline-block' : 'none';
    }
    list.innerHTML = notifs.slice(0, 3).map(function(n) {
      var icon  = notifIcon(n.type);
      var color = NOTIF_COLORS[n.type] || NOTIF_COLORS.info;
      var aUrl  = n.action_url ? escHtml(n.action_url) : '';
      var action = aUrl
        ? 'onclick="handleNotifClick(' + n.id + ',\'' + aUrl + '\')"'
        : 'onclick="goto(\'notifications\')"';
      return '<div class="ncard' + (!n.is_read ? ' ncard-unread' : '') + '" ' + action + ' style="--nc:' + color + ';margin-bottom:.5rem">'
        + '<div class="ncard-icon" style="background:' + color + '">' + icon + '</div>'
        + '<div class="ncard-body">'
        +   '<div class="ncard-title">' + escHtml(n.title) + '</div>'
        +   '<div class="ncard-meta"><span class="ncard-time">' + timeAgo(n.created_at) + '</span></div>'
        + '</div>'
        + '</div>';
    }).join('');
    sec.style.display = 'block';
    updateNotifBadge(unreadCount);
  } catch(e) { sec.style.display = 'none'; }
}

/* Genera HTML de una tarjeta de notificacion */
function renderNotifCard(n) {
  var icon    = notifIcon(n.type);
  var color   = NOTIF_COLORS[n.type] || NOTIF_COLORS.info;
  var unread  = !n.is_read;
  var aUrl    = n.action_url ? escHtml(n.action_url) : '';
  var action  = aUrl
    ? 'onclick="handleNotifClick(' + n.id + ',\'' + aUrl + '\')"'
    : 'onclick="markNotifRead(' + n.id + ')"';
  var globalTag = n.is_global
    ? '<span class="ncard-global">🌐 Para todos</span>'
    : '';
  var typeLabels = {
    course:'Curso', course_done:'Completado', lesson:'Lección',
    product:'Producto', poll:'Encuesta', cert:'Certificado',
    bundle:'Paquete', progress:'Progreso', info:'Info', system:'Sistema',
    directory:'Directorio', directory_admin:'Directorio'
  };
  var typeLabel = typeLabels[n.type] || n.type || 'Info';
  var viewBtn = aUrl
    ? '<button class="ncard-view" onclick="event.stopPropagation();handleNotifClick(' + n.id + ',\'' + aUrl + '\')" style="color:' + color + '">🔗 Ver</button>'
    : '';
  var delBtn = '<button class="ncard-del" onclick="event.stopPropagation();deleteNotif(' + n.id + ')" title="Borrar notificación">✕</button>';
  return '<div class="ncard' + (unread ? ' ncard-unread' : '') + '" id="nc-' + n.id + '" ' + action + ' style="--nc:' + color + '">'
    + '<div class="ncard-icon" style="background:' + color + '">' + icon + '</div>'
    + '<div class="ncard-body">'
    +   '<div class="ncard-row1">'
    +     '<span class="ncard-type" style="color:' + color + '">' + escHtml(typeLabel) + '</span>'
    +     globalTag
    +     (unread ? '<span class="ncard-dot"></span>' : '')
    +     delBtn
    +   '</div>'
    +   '<div class="ncard-title">' + escHtml(n.title) + '</div>'
    +   '<div class="ncard-msg">' + escHtml(n.message) + '</div>'
    +   '<div class="ncard-meta"><span class="ncard-time">' + timeAgo(n.created_at) + '</span>' + viewBtn + '</div>'
    + '</div>'
    + '</div>';
}

/* ── openCourseFromNotif ────────────────────────────────────────────
   Desde notificación/deep-link: abre la vista correcta según inscripción.
   • Inscrito    → openCourse()       (lecciones + progreso)
   • No inscrito → openPublicCourse() (precio + botón de pago)
   Nunca llama openCourse() en un curso no inscrito, evitando el error
   "No estás inscrito en este curso".
───────────────────────────────────────────────────────────────────── */
async function openCourseFromNotif(courseId) {
  var id = parseInt(courseId, 10);
  if (!id) return;

  // Cargar lista de cursos si está vacía
  if (!Array.isArray(ST.courses) || ST.courses.length === 0) {
    var _cd = await getCached(apiURL('/vk/v1/my-courses'),120000);
    ST.courses = (_cd && Array.isArray(_cd.data)) ? _cd.data : (Array.isArray(ST.courses)?ST.courses:[]);
  }
  if(!Array.isArray(ST.courses)) ST.courses = [];

  var enrolled = ST.courses.find(function(c) { return parseInt(c.id, 10) === id; });
  if (enrolled) {
    openCourse(id);
  } else {
    openPublicCourse(id);
  }
}
/* Enrutador de enlaces profundos (Deep Links) de la app */
/* Cubre: URL con params ?open_X=ID, URLs de WordPress, y objetos {type,id} */
function routeAppUrl(url) {
  if (!url) return false;
  // 1. Parseo por parámetros de URL (formato principal)
  try {
    var u = (url.indexOf('http') === 0)
          ? new URL(url)
          : new URL(url, window.location.origin);
    var p = u.searchParams;
    if (p.get('open_course'))  { openCourseFromNotif(p.get('open_course')); return true; }
    if (p.get('open_lesson'))  { openLesson(p.get('open_lesson'));                                        return true; }
    if (p.get('open_product')) { openProductDetail(p.get('open_product'));  return true; }
    if (p.get('open_poll'))    { openPoll(p.get('open_poll'));              return true; }
    if (p.get('open_cert'))    { downloadCertificate(p.get('open_cert'));   return true; }
    if (p.get('open_bundle'))  { openBundle(p.get('open_bundle'));          return true; }
    if (p.get('open_notif'))   { goto('notifications');                     return true; }
    if (p.get('open_section')) { goto(p.get('open_section'));               return true; }
    // URL apunta a la app pero sin parámetros: ir a home
    if (u.hostname === 'app.vidakushala.com') { goto('home'); return true; }
  } catch(e) {}
  // 2. Fallback regex (por si la URL llega sin parsear)
  var _m;
  if ((_m = url.match(/[?&]open_course=(\d+)/)))  { openCourseFromNotif(_m[1]); return true; }
  if ((_m = url.match(/[?&]open_product=(\d+)/))) { openProductDetail(_m[1]);  return true; }
  if ((_m = url.match(/[?&]open_poll=(\d+)/)))    { openPoll(_m[1]);           return true; }
  if ((_m = url.match(/[?&]open_cert=(\d+)/)))    { downloadCertificate(_m[1]); return true; }
  if ((_m = url.match(/[?&]open_bundle=(\d+)/)))  { openBundle(_m[1]);         return true; }

  // 3. URLs de WordPress: detectar tipo por path y redirigir dentro de la app
  if (/\/(?:courses?|cursos?)\/[\w-]+/i.test(url)) {
    goto('courses'); return true;
  }
  if (/\/(?:products?|productos?)\/[\w-]+/i.test(url)) {
    goto('products'); return true;
  }
  if (/\/(?:encuesta|poll|yop.poll)/i.test(url)) {
    goto('polls'); return true;
  }
  if (/\/(?:bundle|paquete)\/[\w-]+/i.test(url)) {
    goto('bundles'); return true;
  }

  // 4. URL de la PWA (app.vidakushala.com) sin parámetro reconocido -> home
  if (url.indexOf('app.vidakushala.com') >= 0) { goto('home'); return true; }

  return false;
}

/* Manejar clic en notificación */
function handleNotifClick(id, url) {
  markNotifRead(id, false);
  // Actualizar UI optimistamente
  var card = document.getElementById('nc-'+id);
  if (card) {
    card.classList.remove('is-unread');
    var dot = card.querySelector('.notif-dot');
    if (dot) dot.remove();
  }
  if (url) {
    var isAppUrl = url.indexOf('app.vidakushala.com') >= 0
                || url.indexOf('localhost') >= 0;
    var isWpUrl  = !isAppUrl && url.indexOf('vidakushala.com') >= 0;
    // URLs del sitio WP (perfiles, directorio, etc.) → nueva ventana
    // URLs externas (Zoom, YouTube, etc.) → nueva ventana
    if (isWpUrl || (!isAppUrl && (url.startsWith('http://') || url.startsWith('https://')))) {
      window.open(url, '_blank', 'noopener,noreferrer');
    } else {
      var routed = routeAppUrl(url);
      if (!routed) goto('notifications');
    }
  }
}

/* Marcar una notificación como leída */
async function markNotifRead(id, reload) {
  try {
    await fetch(apiURL('/vk/v1/notifications/read'), {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({vk_token: ST.token, id: id})
    });
    if (reload !== false) loadNotifications();
  } catch(e) {}
}

/* Marcar todas como leídas */
async function markAllNotifsRead() {
  try {
    await fetch(apiURL('/vk/v1/notifications/read'), {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({vk_token: ST.token, all: true})
    });
    loadNotifications();
    updateNotifBadge(0);
  } catch(e) {}
}

/* Borrar solo las notificaciones leídas */
async function deleteReadNotifs() {
  var btn = document.getElementById('notif-del-read');
  if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Borrando...'; }
  try {
    await fetch(apiURL('/vk/v1/notifications/delete'), {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({vk_token: ST.token, read: true})
    });
    // Animar salida de todas las leídas
    var readSec = document.getElementById('notif-read-section');
    if (readSec) {
      readSec.style.transition = 'opacity .3s,transform .3s';
      readSec.style.opacity = '0';
      readSec.style.transform = 'translateY(10px)';
      setTimeout(function() { loadNotifications(); }, 320);
    } else {
      loadNotifications();
    }
  } catch(e) {
    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-trash-alt"></i> Borrar le&#237;das'; }
  }
}

/* Borrar una notificación */
async function deleteNotif(id) {
  // Animación de salida
  var card = document.getElementById('nc-' + id);
  if (card) {
    card.style.transition = 'all 0.25s ease';
    card.style.opacity = '0';
    card.style.transform = 'translateX(60px)';
    card.style.maxHeight = card.offsetHeight + 'px';
    setTimeout(function() {
      card.style.maxHeight = '0';
      card.style.padding = '0';
      card.style.margin = '0';
      card.style.overflow = 'hidden';
    }, 200);
    setTimeout(function() { card.remove(); }, 450);
  }
  // Actualizar datos locales
  _notifData = _notifData.filter(function(n){ return n.id !== id; });
  // Llamar al backend
  try {
    await fetch(apiURL('/vk/v1/notifications/delete'), {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({vk_token: ST.token, id: id})
    });
  } catch(e) {}
  // Actualizar badge
  var unreadCount = _notifData.filter(function(n){ return !n.is_read; }).length;
  updateNotifBadge(unreadCount);
  var countEl = document.getElementById('notif-unread-count');
  if (countEl) countEl.textContent = unreadCount;
  // Mostrar vacío si no quedan
  var empty = document.getElementById('notif-empty');
  if (_notifData.length === 0 && empty) empty.style.display = 'block';
}

/* Borrar todas las notificaciones leídas */
async function deleteAllReadNotifs() {
  if (!confirm('¿Borrar todas las notificaciones leídas?')) return;
  // Quitar de UI
  _notifData.filter(function(n){ return n.is_read; }).forEach(function(n){
    var card = document.getElementById('nc-' + n.id);
    if (card) card.remove();
  });
  _notifData = _notifData.filter(function(n){ return !n.is_read; });
  var readSec = document.getElementById('notif-read-section');
  if (readSec) readSec.style.display = 'none';
  if (_notifData.length === 0) {
    var empty = document.getElementById('notif-empty');
    if (empty) empty.style.display = 'block';
  }
  // Backend: borrar leídas
  try {
    await fetch(apiURL('/vk/v1/notifications/delete'), {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({vk_token: ST.token, read: true})
    });
  } catch(e) {}
}

/* Colapsar/expandir sección de leídas */
function toggleReadSection() {
  _readSectionCollapsed = !_readSectionCollapsed;
  var listEl = document.getElementById('notif-read-list');
  var btn    = document.getElementById('notif-collapse-btn');
  if (listEl) listEl.style.display = _readSectionCollapsed ? 'none' : 'block';
  if (btn)    btn.textContent = _readSectionCollapsed ? 'Mostrar' : 'Ocultar';
}


/* Filtrar notificaciones por tipo */
var _notifFilter = '';
function filterNotifs(btn, type) {
  _notifFilter = type;
  document.querySelectorAll('.nfilter-btn').forEach(function(b){ b.classList.remove('nfilter-active'); });
  if (btn) btn.classList.add('nfilter-active');
  var filtered = type ? _notifData.filter(function(n){ return n.type === type || (type === 'course' && (n.type === 'course' || n.type === 'course_done' || n.type === 'progress' || n.type === 'lesson')); }) : _notifData;
  var unread = filtered.filter(function(n){ return !n.is_read; });
  var read   = filtered.filter(function(n){ return n.is_read; });
  var unreadSec = document.getElementById('notif-unread-section');
  var readSec   = document.getElementById('notif-read-section');
  var empty     = document.getElementById('notif-empty');
  var countEl   = document.getElementById('notif-unread-count');
  var unreadList = document.getElementById('notif-unread-list');
  var readList   = document.getElementById('notif-read-list');
  if (unreadSec) unreadSec.style.display = unread.length > 0 ? 'block' : 'none';
  if (readSec)   readSec.style.display   = read.length > 0 ? 'block' : 'none';
  if (empty)     empty.style.display     = filtered.length === 0 ? 'block' : 'none';
  if (countEl)   countEl.textContent     = unread.length;
  if (unreadList) unreadList.innerHTML   = unread.map(renderNotifCard).join('');
  if (readList)   readList.innerHTML     = read.map(renderNotifCard).join('');
}

/* Polling automático cada 60s para actualizar badge */
function startNotifPolling() {
  _notifPollTimer = setInterval(async function() {
    try {
      var d = await getJSON(apiURL('/vk/v1/notifications/count'));
      updateNotifBadge(d.count || 0);
    } catch(e) {}
  }, 60000);
}

/* Cargar solo el badge al iniciar sesión */
async function initNotifBadge() {
  try {
    var d = await getJSON(apiURL('/vk/v1/notifications/count'));
    updateNotifBadge(d.count || 0);
    startNotifPolling();
  } catch(e) {}
}



/* ═══════════════════════════════════════════════════════════════════
   CABECERA MÓVIL — menú desplegable + avatar + badge
═══════════════════════════════════════════════════════════════════ */
function updateMobileHeader() {
  var user = ST.user || {};
  var avatarHTML = user.avatar
    ? '<img src="' + user.avatar + '" alt="Avatar">'
    : '<i class="fas fa-user"></i>';

  // Actualizar TODOS los avatares móviles (por clase)
  document.querySelectorAll('.mhdr-avatar').forEach(function(el) {
    el.innerHTML = avatarHTML;
  });

  // Avatar en header del dropdown
  var ddAvatar = document.getElementById('mhdr-dd-avatar');
  if (ddAvatar) ddAvatar.innerHTML = avatarHTML;

  // Nombre y email en dropdown
  var nameEl  = document.getElementById('mhdr-dd-name');
  var emailEl = document.getElementById('mhdr-dd-email');
  if (nameEl)  nameEl.textContent  = user.name  || 'Usuario';
  if (emailEl) emailEl.textContent = user.email || '';
}

function toggleMobileMenu(triggerEl) {
  var dropdown = document.getElementById('mhdr-dropdown');
  if (!dropdown) return;

  if (dropdown.classList.contains('mhdr-open')) {
    closeMobileMenu();
    return;
  }

  // Marcar el .mhdr-user clickeado
  var trigger = triggerEl;
  if (trigger && !trigger.classList.contains('mhdr-user')) {
    trigger = (trigger.closest && trigger.closest('.mhdr-user')) || null;
  }
  document.querySelectorAll('.mhdr-user').forEach(function(u){ u.classList.remove('open'); });
  if (trigger && trigger.classList) trigger.classList.add('open');

  dropdown.classList.add('mhdr-open');

}

// NO usamos document click listener — el menú se cierra via closeMobileMenu()
// llamado desde goto(), openCourse(), y los botones del propio dropdown.
// Esto evita interferir con event.stopPropagation() de otros botones.

function closeMobileOnOutside(e) { /* vacío — ya no se usa */ }

function closeMobileMenu() {
  document.querySelectorAll('.mhdr-user').forEach(function(u){ u.classList.remove('open'); });
  var dropdown = document.getElementById('mhdr-dropdown');
  if (dropdown) dropdown.classList.remove('mhdr-open');

}

function updateMobileNotifBadge(count) {
  // Actualizar TODOS los badges móviles (por clase)
  document.querySelectorAll('.mhdr-notif-badge').forEach(function(badge) {
    if (count > 0) {
      badge.textContent   = count > 99 ? '99+' : count;
      badge.style.display = 'flex';
    } else {
      badge.style.display = 'none';
    }
  });
}

/* ═══════════════════════════════════════════════════════════════════
   DESKTOP TOPBAR — actualizar avatar, badge, nombre
═══════════════════════════════════════════════════════════════════ */
function updateDesktopTopbar() {
  if (window.innerWidth < 1025) return;
  var user = ST.user || {};

  // Avatar
  var avatarEl = document.getElementById('dtb-avatar');
  if (avatarEl) {
    if (user.avatar) {
      avatarEl.innerHTML = '<img src="' + user.avatar + '" alt="Avatar" style="width:100%;height:100%;object-fit:cover" onerror="this.style.display=none">';
    } else {
      avatarEl.innerHTML = '<i class="fas fa-user"></i>';
    }
  }

  // Nombre y email en dropdown
  var nameEl  = document.getElementById('dtb-dd-name');
  var emailEl = document.getElementById('dtb-dd-email');
  if (nameEl)  nameEl.textContent  = user.name  || 'Usuario';
  if (emailEl) emailEl.textContent = user.email || '';
}

function toggleDtbMenu() {
  var menu     = document.getElementById('dtb-user-menu');
  var dropdown = document.getElementById('dtb-dropdown');
  if (!menu || !dropdown) return;

  var isOpen = dropdown.classList.contains('dtb-open');
  if (isOpen) {
    closeDtbMenu();
  } else {
    menu.classList.add('open');
    dropdown.classList.add('dtb-open');
    // Posicionar el dropdown correctamente
    var topbar = document.getElementById('desktop-topbar');
    if (topbar) {
      var rect = topbar.getBoundingClientRect();
      dropdown.style.top   = (rect.bottom + 8) + 'px';
      dropdown.style.right = '1.5rem';
    }
    // Delay 150ms para que el click actual no dispare closeDtbOnOutside inmediatamente
    setTimeout(function() {
      if (dropdown.classList.contains('dtb-open')) {
        document.addEventListener('click', closeDtbOnOutside);
      }
    }, 150);
  }
}

function closeDtbOnOutside(e) {
  var menu     = document.getElementById('dtb-user-menu');
  var dropdown = document.getElementById('dtb-dropdown');
  if (!dropdown) return;
  if (!menu || !menu.contains(e.target)) {
    closeDtbMenu();
    document.removeEventListener('click', closeDtbOnOutside);
  }
}

function closeDtbMenu() {
  var menu     = document.getElementById('dtb-user-menu');
  var dropdown = document.getElementById('dtb-dropdown');
  if (menu)     menu.classList.remove('open');
  if (dropdown) dropdown.classList.remove('dtb-open');
  document.removeEventListener('click', closeDtbOnOutside);
}

function updateDtbNotifBadge(count) {
  var badge = document.getElementById('dtb-notif-badge');
  if (!badge) return;
  if (count > 0) {
    badge.textContent = count > 99 ? '99+' : count;
    badge.style.display = 'flex';
  } else {
    badge.style.display = 'none';
  }
}

/* ═══════════════════════════════════════════════════════════
   ONESIGNAL — Sistema completo de notificaciones push
   App ID: 5ed3833a-c6c4-4b09-9f3c-3d7778e334b4
═══════════════════════════════════════════════════════════ */
var _osReady     = false;
var _osInitDone  = false;
var _playerIdSaved = false;

function initOneSignal() {
  if (_osInitDone) return;
  _osInitDone = true;

  window.OneSignalDeferred = window.OneSignalDeferred || [];
  window.OneSignalDeferred.push(async function(OneSignal) {
    try {
      await OneSignal.init({
        appId:             '5ed3833a-c6c4-4b09-9f3c-3d7778e334b4',
        notifyButton:      { enable: false },
        // sw.js YA incluye OneSignalSDK.sw.js — usar el mismo archivo combinado
        serviceWorkerPath: '/sw.js',
        serviceWorkerParam:{ scope: '/' }
      });

      console.log('[VK Push] Init OK | permission:', Notification.permission);

      // Permiso ya concedido → registrar player_id
      var perm = await OneSignal.Notifications.permission;
      if (perm === true || Notification.permission === 'granted') {
        setTimeout(registerPlayerWithBackend, 1500);
      }

      // Cambio de permiso
      OneSignal.Notifications.addEventListener('permissionChange', function(granted) {
        console.log('[VK Push] permissionChange:', granted);
        if (granted) {
          setTimeout(registerPlayerWithBackend, 1000);
          var banner = document.getElementById('notif-push-banner');
          if (banner) banner.style.display = 'none';
          closePushPrompt();
        }
      });

      // Nuevo subscription ID asignado
      if (OneSignal.User && OneSignal.User.PushSubscription) {
        OneSignal.User.PushSubscription.addEventListener('change', function(e) {
          console.log('[VK Push] PushSubscription change:', e && e.current && e.current.id);
          if (e && e.current && e.current.id) setTimeout(registerPlayerWithBackend, 500);
        });
      }

      // Click en notificación
      OneSignal.Notifications.addEventListener('click', function(event) {
        if (!event || !event.notification) return;
        var data = event.notification.additionalData || event.notification.data || {};
        var url  = event.notification.url || data.url || '';
        if (url && typeof routeAppUrl === 'function' && routeAppUrl(url)) return;
        if (data.type) {
          var tid = data.id || data.course_id || data.product_id || data.poll_id || '';
          var map = {
            course:'courses', course_done:'courses', progress:'courses',
            product:'products', poll:'polls', cert:'certificates',
            bundle:'products', lesson:'courses'
          };
          var section = map[data.type] || 'notifications';
          if (tid) {
            var openers = {
              course:openCourseFromNotif, product:openProductDetail,
              poll:openPoll, cert:downloadCertificate, bundle:openBundle, lesson:openLesson
            };
            if (openers[data.type]) { openers[data.type](tid); return; }
          }
          if (typeof goto === 'function') goto(section);
        }
      });

    } catch(e) {
      console.error('[VK Push] Init error:', e.message);
    }

    _osReady = true;
    schedulePromptIfNeeded();
  });
}

/* ── Diagnóstico push desde consola del navegador ───────────────────
   Ejecutar: vkDiagPush() en la consola de Chrome DevTools
──────────────────────────────────────────────────────────────────── */
window.vkDiagPush = async function() {
  var log = [];
  log.push('=== VK Push Diagnosis ===');
  log.push('Notification API: ' + ('Notification' in window ? 'SI' : 'NO'));
  log.push('Permission: ' + (window.Notification ? Notification.permission : 'N/A'));
  log.push('ServiceWorker: ' + ('serviceWorker' in navigator ? 'SI' : 'NO'));
  log.push('OneSignal loaded: ' + (window.OneSignal ? 'SI' : 'NO'));
  log.push('_osReady: ' + _osReady);
  log.push('ST.token: ' + (ST.token ? 'SI (' + ST.token.substring(0,8) + '...)' : 'NO'));

  if (window.OneSignal) {
    try {
      var perm = await OneSignal.Notifications.permission;
      log.push('OS permission: ' + perm);
    } catch(e){ log.push('OS permission error: ' + e.message); }

    try {
      var optedIn = OneSignal.User && OneSignal.User.PushSubscription
        ? OneSignal.User.PushSubscription.optedIn : 'N/A';
      log.push('OS optedIn: ' + optedIn);
    } catch(e){ log.push('OS optedIn error: ' + e.message); }

    try {
      var subId = OneSignal.User && OneSignal.User.PushSubscription
        ? OneSignal.User.PushSubscription.id : null;
      log.push('OS subscription ID: ' + (subId || 'NONE'));
    } catch(e){ log.push('OS subId error: ' + e.message); }
  }

  if ('serviceWorker' in navigator) {
    try {
      var regs = await navigator.serviceWorker.getRegistrations();
      regs.forEach(function(r, i) {
        var s = r.active || r.installing || r.waiting;
        log.push('SW['+i+']: ' + (s ? s.scriptURL : 'no worker') + ' | scope: ' + r.scope);
      });
      if (!regs.length) log.push('SW: NO HAY SERVICE WORKERS REGISTRADOS — esto bloquea las notificaciones push');
    } catch(e){ log.push('SW error: ' + e.message); }
  }

  console.log(log.join('\n'));
  return log;
};

/* Decidir si mostrar el modal de permisos */
/* ════════════════════════════════════════════════════════════
   SISTEMA DE PERMISOS PUSH — versión simplificada y robusta
════════════════════════════════════════════════════════════ */

function schedulePromptIfNeeded() {
  if (!ST.token) return;
  if (!('Notification' in window)) return;
  // Si ya tiene permiso, intentar registrar player_id directamente
  if (Notification.permission === 'granted') {
    setTimeout(registerPlayerWithBackend, 1000);
    return;
  }
  // Si rechazó, no molestar
  if (Notification.permission === 'denied') return;
  // Mostrar el prompt solo si no se ha mostrado en esta sesión
  if (sessionStorage.getItem('vk_push_prompted')) return;
  setTimeout(function() {
    if (!ST.token) return;
    if (Notification.permission !== 'default') return;
    showPushPrompt();
  }, 3000);
}

async function checkPushPermission() {
  var banner = document.getElementById('notif-push-banner');
  if (!banner) return;
  if (!('Notification' in window)) { banner.style.display = 'none'; return; }

  var perm = Notification.permission;

  if (perm === 'denied') {
    banner.style.display = 'block';
    var txt = banner.querySelector('.notif-push-text');
    if (txt) txt.innerHTML = '<strong>Notificaciones bloqueadas</strong><p>Ve a la configuración del navegador (ícono 🔒) para desbloquearlas.</p>';
    var btn = banner.querySelector('.btn-push-enable');
    if (btn) btn.style.display = 'none';
    return;
  }

  if (perm === 'granted') {
    banner.style.display = 'none';
    // Si no hay player_id activo, reintentar registro
    if (!_playerIdSaved) setTimeout(registerPlayerWithBackend, 1000);
    return;
  }

  // 'default'
  banner.style.display = 'block';
  var txt2 = banner.querySelector('.notif-push-text');
  if (txt2) txt2.innerHTML = '<strong>Activa las notificaciones</strong><p>Recibe alertas de cursos y certificados en tiempo real.</p>';
  var btn2 = banner.querySelector('.btn-push-enable');
  if (btn2) btn2.style.display = '';
}

/* Modal de permisos push */
function showPushPrompt() {
  var modal = document.getElementById('push-prompt-modal');
  if (!modal) return;
  modal.style.display = 'flex';
  void modal.offsetWidth;
  modal.style.opacity = '1';
  var box = document.getElementById('push-prompt-box');
  if (box) box.style.transform = 'translateY(0)';
  sessionStorage.setItem('vk_push_prompted', '1');
}

function closePushPrompt() {
  var modal = document.getElementById('push-prompt-modal');
  if (!modal) return;
  modal.style.opacity = '0';
  var box = document.getElementById('push-prompt-box');
  if (box) box.style.transform = 'translateY(20px)';
  setTimeout(function() { modal.style.display = 'none'; }, 400);
}

async function acceptPushPrompt() {
  closePushPrompt();
  if (window.OneSignal) {
    try {
      var granted = await OneSignal.Notifications.requestPermission();
      if (granted) {
        setTimeout(registerPlayerWithBackend, 1500);
        showToast('<i class="fa-solid fa-check"></i> ¡Notificaciones activadas!');
        var banner = document.getElementById('notif-push-banner');
        if (banner) banner.style.display = 'none';
        return;
      }
    } catch(e) {}
  }
  // Fallback nativo
  if ('Notification' in window) {
    var r = await Notification.requestPermission();
    if (r === 'granted') {
      setTimeout(registerPlayerWithBackend, 1500);
      showToast('<i class="fa-solid fa-check"></i> ¡Notificaciones activadas!');
    }
  }
}

async function activatePushFromBanner() {
  var banner = document.getElementById('notif-push-banner');
  if (banner) banner.style.display = 'none';
  await acceptPushPrompt();
}

/* Solicitar permiso push al usuario */
async function requestPushPermission() {
  if (typeof OneSignal !== 'undefined') {
    try {
      var granted = await OneSignal.Notifications.requestPermission();
      if (granted) {
        registerPlayerWithBackend();
        var banner = document.getElementById('notif-push-banner');
        if (banner) banner.style.display = 'none';
        showToast(' ¡Notificaciones activadas!');
      }
    } catch(e) {
      var result = await Notification.requestPermission();
      if (result === 'granted') showToast(' ¡Notificaciones activadas!');
    }
  } else {
    var result = await Notification.requestPermission();
    if (result === 'granted') {
      showToast(' ¡Notificaciones activadas!');
      var banner = document.getElementById('notif-push-banner');
      if (banner) banner.style.display = 'none';
    }
  }
}

function escHtml(s) {
  return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}


/*  ENCUESTAS (parte 2 - fix universal)  */
/* =========================================================
   FIX ENCUESTAS UNIVERSAL
   Carga YOP Poll (/polls) o Fluent Forms (/ff-forms).
   ========================================================= */
(function(){
  var _pollCache = [];
  var _formCache = [];
  var _pollTypeById = {};
  var _pollAnswers = window._pollAnswers || {};
  var _pollVoted = window._pollVoted || {};

  function _api(path){
    if(typeof apiURL === 'function') return apiURL('/vk/v1' + path);
    var base=(window.C&&C.API_BASE)?C.API_BASE:'http://localhost/wp/vk/wp-json';
    var tok=(window.ST&&ST.token)||(window.S&&S.get?S.get('vk_token'):'')||'';
    return base+'/vk/v1'+path+(path.indexOf('?')>=0?'&':'?')+'vk_token='+encodeURIComponent(tok);
  }
  async function _get(path){
    try{
      var r=await fetch(_api(path));
      var d=await r.json().catch(function(){return {};});
      return d||{};
    }catch(e){return {};}
  }
  function _esc(v){return String(v==null?'':v).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}
  function _img(o){return o.featured_image_full||o.featured_image_large||o.featured_image||o.image||o.thumbnail||'';}
  function _arr(d){
    if(Array.isArray(d))return d;
    if(Array.isArray(d.data))return d.data;
    if(Array.isArray(d.forms))return d.forms;
    if(Array.isArray(d.items))return d.items;
    return [];
  }

  window.loadPolls = async function(){
    var el=document.getElementById('polls-list');
    var sum=document.getElementById('polls-summary');
    if(!el)return;
    el.innerHTML='<div class="spinner-wrap"><div class="spinner"></div>Cargando encuestas...</div>';
    if(sum)sum.textContent=' Cargando...';

    var polls=[], forms=[];
    var pd=await _get('/polls');
    polls=_arr(pd);

    var fd=await _get('/ff-forms');
    forms=_arr(fd);

    _pollCache=polls;
    _formCache=forms;
    _pollTypeById={};

    var cards=[];
    polls.forEach(function(p){
      var id=p.id||p.ID;
      if(!id)return;
      _pollTypeById['poll-'+id]='poll';
      var name=_esc(p.name||p.title||'Encuesta');
      var desc=_esc(p.description||'Participa y comparte tu opinión.');
      var active=(p.status==='active'||p.status==='published'||!p.status);
      var img=_img(p);
      var _icon='fa-clipboard-list';
      var _lname=(name||'').toLowerCase();
      if(_lname.indexOf('satisfac')>=0||_lname.indexOf('experienc')>=0) _icon='fa-star';
      else if(_lname.indexOf('tema')>=0||_lname.indexOf('interes')>=0||_lname.indexOf('nuevo')>=0) _icon='fa-lightbulb';
      else if(_lname.indexOf('plataform')>=0||_lname.indexOf('mejora')>=0||_lname.indexOf('feedback')>=0) _icon='fa-cog';
      else if(_lname.indexOf('evento')>=0||_lname.indexOf('taller')>=0) _icon='fa-calendar-alt';
      else if(_lname.indexOf('curso')>=0||_lname.indexOf('aprend')>=0) _icon='fa-graduation-cap';
      var _votes=Number(p.total_votes||0);
      var _qs=Number(p.questions_count||p.total_questions||0);
      var _meta=_qs?_qs+' preguntas':(_votes?_votes+' respuestas':'');
      var _voted=!!(_pollVoted&&_pollVoted['poll-'+id]);
      cards.push('<article class="poll-row" onclick="openPoll(\'poll-'+id+'\')">'
        +'<div class="poll-row-icon"><i class="fas '+_icon+'"></i></div>'
        +'<div class="poll-row-body">'
        +'<h3 class="poll-row-title">'+name+'</h3>'
        +'<p class="poll-row-desc">'+desc+'</p>'
        +'</div>'
        +(_meta?'<span class="poll-row-meta">'+_meta+'</span>':'')
        +'<button class="poll-row-btn'+(_voted?' poll-row-btn--done':'')+'" onclick="event.stopPropagation();openPoll(\'poll-'+id+'\')">'+(_voted?'<i class="fa-solid fa-check"></i> Ya voté':(active?'Responder':'Ver resultados'))+'</button>'
        +'</article>');
    });

    forms.forEach(function(f){
      var id=f.id||f.ID||f.form_id;
      if(!id)return;
      _pollTypeById['form-'+id]='form';
      var name=_esc(f.title||f.name||'Encuesta');
      var desc=_esc(f.description||'Completa esta encuesta y comparte tu opinión.');
      var img=_img(f);
      var _voted2=!!(_pollVoted&&_pollVoted['form-'+id]);
      cards.push('<article class="poll-row" onclick="openPoll(\'form-'+id+'\')">'
        +'<div class="poll-row-icon"><i class="fas fa-clipboard-list"></i></div>'
        +'<div class="poll-row-body">'
        +'<h3 class="poll-row-title">'+name+'</h3>'
        +'<p class="poll-row-desc">'+desc+'</p>'
        +'</div>'
        +'<span class="poll-row-meta">Encuesta</span>'
        +'<button class="poll-row-btn'+(_voted2?' poll-row-btn--done':'')+'" onclick="event.stopPropagation();openPoll(\'form-'+id+'\')">'+(_voted2?'<i class="fa-solid fa-check"></i> Ya respondí':'Responder')+'</button>'
        +'</article>');
    });

    if(sum)sum.textContent=' '+cards.length+' encuesta'+(cards.length===1?' disponible':'s disponibles');
    if(!cards.length){
      el.innerHTML='<div class="poll-empty"><div class="poll-empty-icon"><i class="fas fa-pen-to-square"></i></div><h3>No hay encuestas disponibles</h3><p>Si el panel de WordPress sí tiene encuestas, revisa que exista el endpoint <strong>/vk/v1/polls</strong> o <strong>/vk/v1/ff-forms</strong>.</p></div>';
      return;
    }
    el.innerHTML='<div class="polls-list">'+cards.join('')+'</div>';
  };

  window.openPoll = async function(key){
    var body=document.getElementById('poll-detail-body');
    var short=document.getElementById('poll-title-short');
    if(!body)return;
    if(String(key).indexOf('poll-')!==0 && String(key).indexOf('form-')!==0){
      key='poll-'+key;
    }
    var parts=String(key).split('-');
    var type=parts[0], id=parts.slice(1).join('-');
    if(typeof goto==='function')goto('poll-detail');
    else if(typeof nav==='function')nav('poll-detail');
    body.innerHTML='<div class="spinner-wrap"><div class="spinner"></div>Cargando encuesta...</div>';
    if(short)short.textContent='Cargando...';

    if(type==='form'){
      var fd=await _get('/ff-forms/'+encodeURIComponent(id));
      if(!fd || (!fd.fields && !fd.data && !fd.form)){
        body.innerHTML='<div class="error-card"><h4>Error</h4><p>No se pudo cargar esta encuesta.</p></div>';return;
      }
      var form=fd.data||fd.form||fd;
      if(short)short.textContent=(form.title||form.name||'Encuesta').substring(0,22);
      renderFluentForm(form,id);
      return;
    }

    var pd=await _get('/polls/'+encodeURIComponent(id));
    var poll=pd.data||pd;
    if(!poll || !(poll.name||poll.title)){
      body.innerHTML='<div class="error-card"><h4>Error</h4><p>No se pudo cargar esta encuesta.</p></div>';return;
    }
    if(short)short.textContent=(poll.name||poll.title||'Encuesta').substring(0,22);
    renderYopPoll(poll,id);
  };

  function renderFluentForm(form,id){
    window._surveyForm=form;
    window._surveyAnswers={};
    var fields=form.fields||form.inputs||[];
    var html='<div class="poll-detail-head"><h2>'+_esc(form.title||form.name||'Encuesta')+'</h2>'+(form.description?'<p>'+_esc(form.description)+'</p>':'')+'</div>';
    if(!fields.length){html+='<div class="poll-empty"><div class="poll-empty-icon"></div><p>Esta encuesta no tiene preguntas disponibles.</p></div>';document.getElementById('poll-detail-body').innerHTML=html;return;}
    fields.forEach(function(f,i){
      if(f.element==='input_name')return;
      var name=f.name||('field_'+i), label=_esc(f.label||f.admin_label||('Pregunta '+(i+1)));
      var req=f.required?'<span style="color:#c44d8a;margin-left:.25rem">*</span>':'';
      html+='<section class="poll-question"><p class="poll-question-title">'+label+req+'</p>';
      var opts=f.options||f.choices||[];
      if(f.element==='input_radio'||f.type==='radio'){
        opts.forEach(function(o){var val=_esc(o.value||o.label||o);var lab=_esc(o.label||o.value||o);html+='<label class="poll-option"><input type="radio" name="'+_esc(name)+'" value="'+val+'" onchange="setSurveyAnswer(\''+_esc(name)+'\',this.value,\'radio\')"><span>'+lab+'</span></label>';});
      }else if(f.element==='input_checkbox'||f.type==='checkbox'){
        opts.forEach(function(o){var val=_esc(o.value||o.label||o);var lab=_esc(o.label||o.value||o);html+='<label class="poll-option"><input type="checkbox" value="'+val+'" onchange="setSurveyAnswer(\''+_esc(name)+'\',this.value,\'checkbox\',this.checked)"><span>'+lab+'</span></label>';});
      }else if(f.element==='input_select'||f.element==='select'||f.type==='select'){
        html+='<select class="poll-input" onchange="setSurveyAnswer(\''+_esc(name)+'\',this.value,\'select\')"><option value="">Selecciona...</option>'+opts.map(function(o){return '<option value="'+_esc(o.value||o.label||o)+'">'+_esc(o.label||o.value||o)+'</option>';}).join('')+'</select>';
      }else if(f.element==='textarea'||f.type==='textarea'){
        html+='<textarea class="poll-textarea" placeholder="'+_esc(f.placeholder||'Tu respuesta...')+'" oninput="setSurveyAnswer(\''+_esc(name)+'\',this.value,\'text\')"></textarea>';
      }else{
        var inputType=(f.element==='input_email'||f.type==='email')?'email':(f.element==='input_number'||f.type==='number')?'number':'text';
        html+='<input class="poll-input" type="'+inputType+'" placeholder="'+_esc(f.placeholder||'')+'" oninput="setSurveyAnswer(\''+_esc(name)+'\',this.value,\'text\')">';
      }
      html+='</section>';
    });
    html+='<div id="survey-error" class="vk-poll-msg vk-poll-msg--error" style="display:none"></div><button class="btn btn-primary" id="btn-submit-fluent" onclick="submitFluentForm(\''+_esc(id)+'\')" style="width:100%;margin-top:.25rem">Enviar respuestas</button>';
    document.getElementById('poll-detail-body').innerHTML=html;
  }

  window.setSurveyAnswer=function(name,val,type,checked){
    window._surveyAnswers=window._surveyAnswers||{};
    if(type==='checkbox'){
      if(!Array.isArray(window._surveyAnswers[name]))window._surveyAnswers[name]=[];
      if(checked){if(window._surveyAnswers[name].indexOf(val)<0)window._surveyAnswers[name].push(val);}else{window._surveyAnswers[name]=window._surveyAnswers[name].filter(function(v){return v!==val;});}
    }else window._surveyAnswers[name]=val;
  };

  window.submitFluentForm=async function(id){
    var btn=document.getElementById('btn-submit-fluent');
    var errEl=document.getElementById('survey-error');
    if(btn){btn.disabled=true;btn.textContent='Enviando...';btn.style.opacity='.7';}
    if(errEl)errEl.style.display='none';
    try{
      var r=await fetch(_api('/ff-forms/'+encodeURIComponent(id)+'/submit'),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({answers:window._surveyAnswers||{}})});
      var d=await r.json().catch(function(){return {};});
      if(r.ok&&(d.success||d.status==='success')){
        document.getElementById('poll-detail-body').innerHTML='<div class="vk-poll-success"><div class="vk-poll-success-icon"><i class="fa-solid fa-check"></i></div><h3>¡Respuesta enviada!</h3><p>Tu opinión ha sido registrada correctamente. ¡Gracias por participar!</p><button onclick="goto(\'polls\')" class="btn btn-primary" style="margin-top:1.25rem;max-width:240px">Ver más encuestas</button></div>';
      }else{
        if(btn){btn.disabled=false;btn.textContent='Enviar respuestas';btn.style.opacity='';}
        if(errEl){errEl.textContent=' '+(d.message||'Error al enviar. Intenta de nuevo.');errEl.style.display='flex';}
      }
    }catch(e){
      if(btn){btn.disabled=false;btn.textContent='Enviar respuestas';btn.style.opacity='';}
      if(errEl){errEl.textContent=' Error de conexión. Verifica tu internet.';errEl.style.display='flex';}
    }
  };

  function renderYopPoll(poll,id){
    _pollAnswers={}; window._pollAnswers=_pollAnswers;
    var voted=!!_pollVoted[id];
    var active=(poll.status==='active'||poll.status==='published'||!poll.status);
    var questions=poll.questions||[];
    var totalVotes=Number(poll.total_votes||0);
    var html='<div class="poll-detail-head"><h2>'+_esc(poll.name||poll.title||'Encuesta')+'</h2>'+(poll.description?'<p>'+_esc(poll.description)+'</p>':'')+'<div class="poll-meta-badge"> '+totalVotes+' respuestas</div></div>';
    if(voted){
      html+='<div class="vk-poll-msg vk-poll-msg--voted"> Ya has votado en esta encuesta. ¡Gracias por tu participación!</div>';
    }
    if(!questions.length){html+='<div class="poll-empty"><div class="poll-empty-icon"></div><p>Esta encuesta no tiene preguntas disponibles.</p></div>';document.getElementById('poll-detail-body').innerHTML=html;return;}
    questions.forEach(function(q,qi){
      var qid=q.id||q.ID,total=Math.max(q.total_votes||0,1);
      html+='<section class="poll-question"><p class="poll-question-title">'+(qi+1)+'. '+_esc(q.text||q.title||'Pregunta')+'</p>';
      (q.options||[]).forEach(function(opt){
        var oid=opt.id||opt.ID,pct=Math.round(((opt.votes||0)/total)*100);
        if(voted||!active){
          html+='<div class="poll-result"><div class="poll-result-row"><span>'+_esc(opt.text||opt.title||'Opción')+'</span><span class="poll-result-pct">'+pct+'%</span></div><div class="poll-result-bar"><div class="poll-result-fill" style="width:'+pct+'%"></div></div><p class="poll-result-votes">'+Number(opt.votes||0)+' votos</p></div>';
        }else{
          html+='<div onclick="togglePollAnswer('+qid+','+oid+','+(q.multiple?1:0)+')" id="popt-'+qid+'-'+oid+'" class="poll-option"><div class="poll-box '+(q.multiple?'multi':'')+'"></div><span>'+_esc(opt.text||opt.title||'Opción')+'</span></div>';
        }
      });
      html+='</section>';
    });
    if(!voted&&active){
      html+='<div id="poll-submit-wrap" style="margin-top:.5rem"><div id="poll-error-msg" class="vk-poll-msg vk-poll-msg--error" style="display:none"></div><button onclick="submitPoll('+id+')" id="btn-submit-poll" class="btn btn-primary" style="width:100%">Enviar respuestas</button></div>';
    }else if(!active){
      html+='<div class="poll-status closed"> Encuesta cerrada</div>';
    }
    document.getElementById('poll-detail-body').innerHTML=html;
  }
})();

/* ═══════════════════════════════════════════════════════
   AI CHAT PREMIUM — Nativo con proxy VK → MWAI
   /vk/v1/aichat-send maneja auth y llama a AI Engine internamente
   (no necesita cookies de sesión WP ni nonce del navegador)
═══════════════════════════════════════════════════════ */
var _vkc = { ready:false, sending:false, botId:null, sid:null, history:[], accessOk:null };

async function openAiChat() {
  var tok = (window.ST && ST.token) ? ST.token : (window.S ? S.get('vk_token') : '');
  if (!tok) { goto('login'); return; }

  // Solo verificar acceso si aun no fue aprobado en esta sesion.
  // Repetirlo cada vez causaba que una respuesta lenta (~30 s) llegara
  // mientras el usuario chateaba y borrara el chat al ejecutar un 403.
  if (_vkc.accessOk !== true) {
    try {
      var ar = await fetch(C.API_BASE + '/vk/v1/aichat-access?vk_token=' + encodeURIComponent(tok));
      var ad = await ar.json();
      _vkc.accessOk = !!(ad.has_access);
      var scStr = (ad.product || {}).agent_shortcode || '';
      var bm = scStr.match(/id=["']?([a-zA-Z0-9_\-]+)["']?/);
      if (bm && bm[1]) _vkc.botId = bm[1];
      if (!_vkc.accessOk) {
        showAiChatProduct(ad.product || {});
        return;
      }
    } catch(e) {
      if (_vkc.accessOk === null) _vkc.accessOk = true;
      if (!_vkc.accessOk) { showAiChatProduct({}); return; }
    }
  }

  // ── 2. Navegar a screen-chat ───────────────────────────────────────
  document.querySelectorAll('.screen').forEach(function(s){ s.classList.remove('active','exit'); });
  var sc = document.getElementById('screen-chat');
  if (sc) { sc.classList.add('active'); sc.classList.remove('has-wall'); }
  document.querySelectorAll('.nav-item,.snav-item').forEach(function(n){ n.classList.remove('active'); });
  window.scrollTo(0, 0);
  if (window.m3cApplyChrome) setTimeout(window.m3cApplyChrome, 0);

  // Ocultar bottom-nav — el input del chat necesita ese espacio
  var bnav = document.getElementById('bottom-nav');
  if (bnav) bnav.style.display = 'none';

  // Ocultar wall — el chat (#vkc-chat) siempre está debajo como position:absolute
  var wall = document.getElementById('vk-ai-wall');
  if (wall) wall.style.display = 'none';
  window._vkcWallVisible = false;

  // ── 3. Inicializar estado ─────────────────────────────────────────
  _vkcInjectStyles();
  _vkc.ready  = true;
  _vkc.botId  = _vkc.botId || 'default';
  _vkc.sid    = _vkc.sid || ('vks' + Date.now());
  if (!_vkc.history) _vkc.history = [];

  // ── 4. Restaurar historial guardado o mostrar saludo ──────────────
  var msgs = document.getElementById('vkc-msgs');
  if (msgs && !msgs.children.length) {
    var saved = _vkcLoadHistory();
    if (saved && saved.length) {
      saved.forEach(function(m){ _vkcBubble(m.role === 'user' ? 'user' : 'ai', m.content); });
    } else {
      _vkcBubble('ai', '¡Hola! 👋 Soy tu asistente VidaKushala. ¿En qué te puedo ayudar hoy?');
    }
  }
  setTimeout(function(){ var t = document.getElementById('vkc-ta'); if (t) t.focus(); }, 250);
}

/* ── Enviar mensaje ─────────────────────────────────────────────── */
async function vkcSend() {
  if (_vkc.sending || !_vkc.ready) return;
  var ta  = document.getElementById('vkc-ta');
  var btn = document.getElementById('vkc-sendbtn');
  if (!ta) return;
  var text = ta.value.trim();
  if (!text) return;

  ta.value = ''; ta.style.height = 'auto'; ta.disabled = true;
  if (btn) { btn.disabled = true; btn.style.opacity = '.45'; }
  _vkc.sending = true;
  _vkc.history.push({ role: 'user', content: text });
  _vkcSaveHistory();
  _vkcBubble('user', text);
  var tid = _vkcTyping();

  var tok = (window.ST && ST.token) ? ST.token : (window.S ? S.get('vk_token') : '');

  // Construir historial de mensajes para MWAI
  var msgs = _vkc.history.slice(0, -1).map(function(h, i) {
    return { id: 'h' + i, role: h.role === 'user' ? 'user' : 'assistant', content: h.content };
  });
  msgs.push({ id: 'cur', role: 'user', content: text });

  try {
    // Llamar al proxy VK que internamente usa mwai_ask_chatbot() o /mwai-ui/v1/chats/submit
    var payload = {
      vk_token:   tok,
      newMessage: text,
      messages:   msgs,
      stream:     false,
      sessionId:  _vkc.sid,
      chatId:     _vkc.botId || 'default',
      botId:      _vkc.botId || 'default'
    };
    var sendUrl = C.API_BASE + '/vk/v1/aichat-send?vk_token=' + encodeURIComponent(tok);
    var abortCtrl = typeof AbortController !== 'undefined' ? new AbortController() : null;
    var abortTid  = abortCtrl ? setTimeout(function(){ abortCtrl.abort(); }, 120000) : null;
    var r = await fetch(sendUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
      signal: abortCtrl ? abortCtrl.signal : undefined
    });
    if (abortTid) clearTimeout(abortTid);
    var d = await r.json();
    _vkcRemTyping(tid);

    // Sin acceso → mostrar pantalla de producto
    if (r.status === 401 || r.status === 403) {
      _vkc.history.pop();
      _vkc.ready = false;
      document.querySelectorAll('.screen').forEach(function(s){ s.classList.remove('active'); });
      showAiChatProduct(d.data && d.data.product ? d.data.product : (d.product || {}));
      return;
    }

    var reply = d.reply || d.result || d.text || '';
    if (!reply) {
      // Extraer mensaje de error real del servidor
      var errMsg = (d.message) ? d.message
                 : (d.data && d.data.log) ? '[Debug] '+d.data.log.join(' | ')
                 : 'El asistente no está disponible. Intenta más tarde.';
      console.error('[VKChat] Error servidor status='+r.status, d);
      reply = errMsg;
    }

    _vkc.history.push({ role: 'assistant', content: reply });
    _vkcSaveHistory();
    _vkcBubble('ai', reply);

  } catch (e) {
    _vkcRemTyping(tid);
    _vkc.history.pop();
    _vkcBubble('ai', 'Error de conexión. Verifica tu red e inténtalo de nuevo.');
    console.error('[VKChat]', e);
  } finally {
    _vkc.sending = false;
    if (ta)  { ta.disabled = false; }
    if (btn) { btn.disabled = false; btn.style.opacity = '1'; }
    setTimeout(function(){ var t = document.getElementById('vkc-ta'); if (t) t.focus(); }, 60);
  }
}


/* Mensajes - estilo MWAI chatgpt.css */
function _vkcBubble(role, text) {
  var msgs = document.getElementById('vkc-msgs');
  if (!msgs) return;
  var isUser = (role === 'user');
  var row = document.createElement('div');
  row.className = 'vkc-reply ' + (isUser ? 'vkc-user' : 'vkc-ai');
  var nameCol = document.createElement('div');
  nameCol.className = 'vkc-name';
  var av = document.createElement('div');
  av.className = 'vkc-avatar';
  av.setAttribute('aria-hidden', 'true');
  if (isUser) {
    av.innerHTML = '<svg width="32" height="32" viewBox="0 0 24 24"><rect width="24" height="24" rx="5" fill="#5c5e6e"/><path d="M12 12c2.2 0 4-1.8 4-4s-1.8-4-4-4-4 1.8-4 4 1.8 4 4 4zm0 2c-2.7 0-8 1.3-8 4v1h16v-1c0-2.7-5.3-4-8-4z" fill="rgba(255,255,255,.8)"/></svg>';
  } else {
    av.innerHTML = '<svg width="32" height="32" viewBox="0 0 24 24"><rect width="24" height="24" rx="5" fill="#19c37d"/><path d="M8.5 9.5a.5.5 0 1 0 1 0 .5.5 0 0 0-1 0Zm6 0a.5.5 0 1 0 1 0 .5.5 0 0 0-1 0Z" fill="#fff"/><path d="M9 13.5s1 1.8 3 1.8 3-1.8 3-1.8" stroke="#fff" stroke-width="1.2" stroke-linecap="round"/><rect x="9.5" y="3.5" width="5" height="3.5" rx="1" fill="#fff" opacity=".85"/><line x1="12" y1="3.5" x2="12" y2="2.5" stroke="#fff" stroke-width="1.3" stroke-linecap="round"/></svg>';
  }
  nameCol.appendChild(av);
  var label = document.createElement('span');
  label.className = 'vkc-name-text';
  label.textContent = isUser ? 'Tú' : 'VK AI';
  nameCol.appendChild(label);
  row.appendChild(nameCol);
  var txt = document.createElement('div');
  txt.className = 'vkc-text';
  var span = document.createElement('span');
  span.innerHTML = _vkcMd(text);
  txt.appendChild(span);
  row.appendChild(txt);
  msgs.appendChild(row);
  msgs.scrollTop = msgs.scrollHeight;
}

/* Persistencia del historial en localStorage */
function _vkcSaveHistory() {
  try { localStorage.setItem('_vkc_hist', JSON.stringify(_vkc.history.slice(-40))); } catch(e){}
}
function _vkcLoadHistory() {
  try { var h = localStorage.getItem('_vkc_hist'); return h ? JSON.parse(h) : null; } catch(e){ return null; }
}
function _vkcClearHistory() {
  try { localStorage.removeItem('_vkc_hist'); } catch(e){}
}

/* Borrar conversación */
function vkcClear() {
  var msgs = document.getElementById('vkc-msgs');
  if (msgs) msgs.innerHTML = '';
  _vkc.history = [];
  _vkc.sid = 'vks' + Date.now();
  _vkcClearHistory();
  if (_vkcTimerIv) { clearInterval(_vkcTimerIv); _vkcTimerIv = null; }
  var ta = document.getElementById('vkc-ta');
  if (ta) { ta.value = ''; ta.style.height = 'auto'; ta.focus(); }
  _vkcBubble('ai', '¡Conversación borrada! 🗑️ ¿En qué te puedo ayudar?');
}

/* Indicador de escritura con contador de tiempo */
var _vkcTimerIv = null;
function _vkcTyping() {
  var msgs = document.getElementById('vkc-msgs');
  if (!msgs) return null;
  var id = 'vkct' + Date.now();
  var row = document.createElement('div');
  row.id = id;
  row.className = 'vkc-reply vkc-ai';
  var nameCol = document.createElement('div');
  nameCol.className = 'vkc-name';
  var av = document.createElement('div');
  av.className = 'vkc-avatar';
  av.setAttribute('aria-hidden', 'true');
  av.innerHTML = '<svg width="32" height="32" viewBox="0 0 24 24"><rect width="24" height="24" rx="5" fill="#19c37d"/><path d="M8.5 9.5a.5.5 0 1 0 1 0 .5.5 0 0 0-1 0Zm6 0a.5.5 0 1 0 1 0 .5.5 0 0 0-1 0Z" fill="#fff"/><path d="M9 13.5s1 1.8 3 1.8 3-1.8 3-1.8" stroke="#fff" stroke-width="1.2" stroke-linecap="round"/></svg>';
  nameCol.appendChild(av);
  var label = document.createElement('span');
  label.className = 'vkc-name-text';
  label.textContent = 'VK AI';
  nameCol.appendChild(label);
  row.appendChild(nameCol);
  var txt = document.createElement('div');
  txt.className = 'vkc-text';
  txt.innerHTML = '<div class="vkc-dots"><span></span><span></span><span></span></div><div class="mwai-timer">0:00</div>';
  row.appendChild(txt);
  msgs.appendChild(row);
  msgs.scrollTop = msgs.scrollHeight;
  // Iniciar contador
  var t0 = Date.now();
  if (_vkcTimerIv) clearInterval(_vkcTimerIv);
  _vkcTimerIv = setInterval(function() {
    var el = document.querySelector('#' + id + ' .mwai-timer');
    if (!el) { clearInterval(_vkcTimerIv); _vkcTimerIv = null; return; }
    var s = Math.floor((Date.now() - t0) / 1000);
    el.textContent = Math.floor(s / 60) + ':' + (s % 60 < 10 ? '0' : '') + (s % 60);
  }, 1000);
  return id;
}
function _vkcRemTyping(id) {
  if (_vkcTimerIv) { clearInterval(_vkcTimerIv); _vkcTimerIv = null; }
  var el = id && document.getElementById(id); if (el) el.remove();
}

/* ── Markdown renderer — parser line-by-line ──────────────────── */

function _vkcEsc(t) {
  return t.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function _vkcInline(t) {
  t = _vkcEsc(t);
  // Bold+italic
  t = t.replace(/\*\*\*([^*]+?)\*\*\*/g, '<strong><em>$1</em></strong>');
  // Bold
  t = t.replace(/\*\*([^*\n]+?)\*\*/g, '<strong>$1</strong>');
  t = t.replace(/__([^_\n]+?)__/g, '<strong>$1</strong>');
  // Italic
  t = t.replace(/\*([^*\n]+?)\*/g, '<em>$1</em>');
  t = t.replace(/_([^_\n]+?)_/g, '<em>$1</em>');
  // Inline code
  t = t.replace(/`([^`\n]+?)`/g, '<code class="vkc-icode">$1</code>');
  // Links
  t = t.replace(/\[([^\]]+)\]\((https?:\/\/[^)]+)\)/g,
    '<a href="$2" target="_blank" rel="noopener noreferrer" class="vkc-link">$1</a>');
  return t;
}

function _vkcParseTable(rows) {
  // rows: array of raw strings like "| A | B | C |"
  // Find separator row (|---|---|)
  var sepIdx = -1;
  for (var i = 0; i < rows.length; i++) {
    if (/^\s*\|[\s\-:|]+\|\s*$/.test(rows[i])) { sepIdx = i; break; }
  }
  var hasHead = sepIdx === 1;

  function rowHtml(raw, tag) {
    var cells = raw.trim().replace(/^\||\|$/g, '').split('|');
    return '<tr>' + cells.map(function(c) {
      return '<' + tag + '>' + _vkcInline(c.trim()) + '</' + tag + '>';
    }).join('') + '</tr>';
  }

  var html = '<div class="vkc-table-wrap"><table class="vkc-table">';
  if (hasHead) {
    html += '<thead>' + rowHtml(rows[0], 'th') + '</thead><tbody>';
    for (var j = 2; j < rows.length; j++) html += rowHtml(rows[j], 'td');
    html += '</tbody>';
  } else {
    html += '<tbody>';
    rows.forEach(function(r, ri) { if (ri !== sepIdx) html += rowHtml(r, 'td'); });
    html += '</tbody>';
  }
  return html + '</table></div>';
}

function _vkcMd(t) {
  if (!t) return '';

  // Normalize line endings
  t = t.replace(/\r\n/g, '\n').replace(/\r/g, '\n');

  // 1. Extract fenced code blocks → placeholders
  var codeStore = [];
  t = t.replace(/```([^\n`]*)\n?([\s\S]*?)```/g, function(_, lang, code) {
    var i = codeStore.length;
    codeStore.push({ lang: lang.trim(), code: code.replace(/^\n|\n$/g, '') });
    return '\x00CB' + i + '\x00';
  });

  var lines = t.split('\n');
  var out   = [];
  var i     = 0;

  while (i < lines.length) {
    var ln = lines[i];

    // ── Code block placeholder ──────────────────────────────
    var cbM = ln.trim().match(/^\x00CB(\d+)\x00$/);
    if (cbM) {
      var cb  = codeStore[parseInt(cbM[1])];
      var la  = cb.lang ? ' data-lang="' + _vkcEsc(cb.lang) + '"' : '';
      out.push('<pre class="vkc-pre"' + la + '><code class="vkc-code">' +
        _vkcEsc(cb.code) + '</code></pre>');
      i++; continue;
    }

    // ── Table: line starts with | ───────────────────────────
    if (/^\s*\|/.test(ln)) {
      var tbl = [];
      while (i < lines.length && /^\s*\|/.test(lines[i])) { tbl.push(lines[i]); i++; }
      if (tbl.length >= 1) out.push(_vkcParseTable(tbl));
      continue;
    }

    // ── Heading ─────────────────────────────────────────────
    var hM = ln.match(/^(#{1,4}) (.+)/);
    if (hM) {
      var lv = hM[1].length;
      out.push('<h' + lv + ' class="vkc-h' + lv + '">' + _vkcInline(hM[2]) + '</h' + lv + '>');
      i++; continue;
    }

    // ── HR ──────────────────────────────────────────────────
    if (/^[-*_]{3,}\s*$/.test(ln)) {
      out.push('<hr class="vkc-hr">'); i++; continue;
    }

    // ── Blockquote ──────────────────────────────────────────
    if (/^>\s?/.test(ln)) {
      var bq = [];
      while (i < lines.length && /^>\s?/.test(lines[i])) {
        bq.push(_vkcInline(lines[i].replace(/^>\s?/, ''))); i++;
      }
      out.push('<blockquote class="vkc-bq">' + bq.join('<br>') + '</blockquote>');
      continue;
    }

    // ── Unordered list ──────────────────────────────────────
    if (/^[\-\*•] /.test(ln)) {
      var uli = [];
      while (i < lines.length && /^[\-\*•] /.test(lines[i])) {
        uli.push('<li class="vkc-liul">' + _vkcInline(lines[i].replace(/^[\-\*•] /, '')) + '</li>');
        i++;
      }
      out.push('<ul class="vkc-ul">' + uli.join('') + '</ul>');
      continue;
    }

    // ── Ordered list ────────────────────────────────────────
    if (/^\d+[\.\)] /.test(ln)) {
      var oli = [];
      while (i < lines.length && /^\d+[\.\)] /.test(lines[i])) {
        oli.push('<li class="vkc-liol">' + _vkcInline(lines[i].replace(/^\d+[\.\)] /, '')) + '</li>');
        i++;
      }
      out.push('<ol class="vkc-ol">' + oli.join('') + '</ol>');
      continue;
    }

    // ── Empty line ──────────────────────────────────────────
    if (!ln.trim()) { out.push(''); i++; continue; }

    // ── Regular text — collect until a block element starts ──
    var para = [];
    while (i < lines.length) {
      var cur = lines[i];
      if (!cur.trim()) break;
      if (/^(#{1,4} |[\-\*•] |\d+[\.\)] |>\s?|[-*_]{3,}\s*$)/.test(cur)) break;
      if (/^\s*\|/.test(cur)) break;
      if (/^\x00CB\d+\x00$/.test(cur.trim())) break;
      para.push(_vkcInline(cur));
      i++;
    }
    if (para.length) out.push(para.join('<br>'));
  }

  // 2. Join — empty lines become spacing (one <br> between blocks)
  var result = '';
  for (var k = 0; k < out.length; k++) {
    if (out[k] === '') {
      // Blank line: add <br> only if between two non-empty block outputs
      if (k > 0 && k < out.length - 1 && out[k-1] !== '' && out[k+1] !== '') {
        result += '<br>';
      }
    } else {
      result += out[k];
    }
  }

  return result;
}

/* Estilos — réplica exacta de mwai-chatgpt-theme
   Variables del plugin:
     --mwai-backgroundPrimaryColor:   #454654  (fondo AI)
     --mwai-backgroundSecondaryColor: #343541  (fondo user / bordes tabla)
     --mwai-fontColor:                #FFFFFF
     --mwai-spacing:                  15px
     --mwai-fontSize:                 15px
     --mwai-lineHeight:               1.5
     --mwai-borderRadius:             10px
*/
function _vkcInjectStyles() {
  if (document.getElementById('vkc-kf')) return;
  var BG1 = '#454654';   // AI message bg  (primary)
  var BG2 = '#343541';   // User msg bg / table borders (secondary)
  var FG  = '#ffffff';
  var st  = document.createElement('style');
  st.id   = 'vkc-kf';
  st.textContent = [
    /* ── Animaciones ───────────────────────────────────── */
    '@keyframes vkcDot{0%,100%{opacity:.3;transform:translateY(0)}50%{opacity:1;transform:translateY(-4px)}}',
    '@keyframes vkcUp{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}',

    /* ── Burbujas ───────────────────────────────────────── */
    '.vkc-reply{display:flex;padding:15px;position:relative;line-height:1.5;animation:vkcUp .2s ease}',
    '.vkc-user{background:'+BG2+'}',
    '.vkc-ai{background:'+BG1+'}',
    '.vkc-name{color:'+FG+';margin-right:15px;display:flex;flex-direction:column;align-items:center;gap:4px;min-width:32px;flex-shrink:0}',
    '.vkc-name-text{opacity:.5;white-space:nowrap;font-size:11px;text-align:center}',
    '.vkc-avatar{display:flex;align-items:center;justify-content:center;border-radius:5px;overflow:hidden;width:32px;height:32px;min-width:32px}',
    '.vkc-text{flex:auto;min-width:0;font-size:15px;line-height:1.5;color:'+FG+';overflow-wrap:anywhere}',
    '.vkc-text > span{display:block}',
    '.vkc-text > span > *:first-child{margin-top:0!important}',
    '.vkc-text > span > *:last-child{margin-bottom:0!important}',

    /* ── Encabezados (idéntico al plugin) ───────────────── */
    '.vkc-h1,.vkc-h2,.vkc-h3,.vkc-h4{color:'+FG+';line-height:1.3;margin:.5em 0 .25em;font-weight:700}',
    '.vkc-h1{font-size:200%}',
    '.vkc-h2{font-size:160%}',
    '.vkc-h3{font-size:140%}',
    '.vkc-h4{font-size:120%}',

    /* ── Código inline (idéntico al plugin) ─────────────── */
    '.vkc-icode{background:'+BG2+';padding:2px 6px;border-radius:8px;font-size:90%;font-family:system-ui}',

    /* ── Bloques de código (idéntico al plugin) ─────────── */
    '.vkc-pre{color:'+FG+';border-radius:10px;padding:10px 15px;white-space:pre-wrap;font-size:95%;width:100%;font-family:system-ui;background:hsl(0 0% 0%/30%);margin:8px 0;overflow-x:auto;box-sizing:border-box;max-width:100%;display:block}',
    '.vkc-pre[data-lang]::before{content:attr(data-lang);float:right;font-size:.72em;color:rgba(255,255,255,.4);text-transform:uppercase;letter-spacing:.06em;margin-top:2px}',
    '.vkc-code{padding:0!important;font-family:system-ui;color:'+FG+';display:block}',

    /* ── Links ──────────────────────────────────────────── */
    '.vkc-link{color:#2196f3}',
    '.vkc-link:hover{text-decoration:underline}',

    /* ── Listas (idéntico al plugin) ────────────────────── */
    '.vkc-ul,.vkc-ol{padding:0}',
    '.vkc-ol{margin:0 0 0 20px}',
    '.vkc-ul{list-style:none;margin:0}',
    '.vkc-liul{padding:.1rem 0 .1rem 1.1rem;position:relative}',
    '.vkc-liul::before{content:"\\2022";position:absolute;left:.1rem;color:'+FG+'}',
    '.vkc-liol{padding:.1rem 0;list-style:decimal;color:'+FG+'}',

    /* ── Blockquote ─────────────────────────────────────── */
    '.vkc-bq{border-left:3px solid rgba(255,255,255,.25);margin:4px 0;padding:2px 10px;color:rgba(255,255,255,.7)}',

    /* ── HR ─────────────────────────────────────────────── */
    '.vkc-hr{border:none;border-top:1px solid rgba(255,255,255,.15);margin:8px 0}',

    /* ── Tablas — idéntico al plugin ────────────────────── */
    /* .mwai-chatgpt-theme .mwai-reply .mwai-text table */
    '.vkc-table-wrap{overflow-x:auto;max-width:100%;margin:8px 0}',
    '.vkc-table{width:100%;border:2px solid '+BG2+';border-collapse:collapse;font-size:inherit}',
    /* thead usa el color secundario como fondo, igual que el plugin */
    '.vkc-table thead{background:'+BG2+'}',
    /* th: texto blanco, mismo padding que plugin (tr,td = 2px 5px) */
    '.vkc-table th{padding:4px 8px;border:2px solid '+BG2+';color:'+FG+';font-weight:600;text-align:left}',
    /* td: border igual que plugin */
    '.vkc-table td{padding:2px 5px;border:2px solid '+BG2+';color:'+FG+'}',
    /* Hover sutil para lectura */
    '.vkc-table tbody tr:hover td{background:rgba(255,255,255,.05)}',

    /* ── Puntos de escritura ────────────────────────────── */
    '.vkc-dots{display:flex;gap:5px;align-items:center;padding:4px 0}',
    '.vkc-dots span{width:8px;height:8px;border-radius:50%;background:rgba(255,255,255,.45);display:inline-block;animation:vkcDot 1.2s ease-in-out infinite}',
    '.vkc-dots span:nth-child(2){animation-delay:.2s}',
    '.vkc-dots span:nth-child(3){animation-delay:.4s}',
    '.mwai-timer{font-size:11px;color:rgba(255,255,255,.5);margin-left:6px;font-variant-numeric:tabular-nums;letter-spacing:.02em}',

    /* ── Scrollbar ──────────────────────────────────────── */
    '#vkc-msgs::-webkit-scrollbar{width:6px}',
    '#vkc-msgs::-webkit-scrollbar-track{background:transparent}',
    '#vkc-msgs::-webkit-scrollbar-thumb{background:rgba(255,255,255,.12);border-radius:3px}',
    '#vkc-ta::placeholder{color:rgba(255,255,255,.5)!important}',

    /* ── Responsive ─────────────────────────────────────── */
    '@media(max-width:760px){.vkc-reply{padding:12px}.vkc-text{font-size:14px}.vkc-name{margin-right:10px}}',
  ].join('');
  document.head.appendChild(st);
}

function showAiChatProduct(product) {
  var name       = product.name        || 'Asistente VK AI';
  var desc       = product.description || 'Accede al asistente de inteligencia artificial personal.';
  var price      = product.price       || '';
  var payUrl     = product.payment_url || '';
  var contactUrl = product.contact_url || '';
  var img        = product.image       || '';

  // Navegar a screen-chat
  document.querySelectorAll('.screen').forEach(function(s){ s.classList.remove('active','exit'); });
  var sc = document.getElementById('screen-chat');
  if (sc) { sc.classList.add('active'); sc.classList.add('has-wall'); }
  document.querySelectorAll('.nav-item,.snav-item').forEach(function(n){ n.classList.remove('active'); });
  window.scrollTo(0, 0);
  if (window.m3cApplyChrome) setTimeout(window.m3cApplyChrome, 0);

  // Mostrar el nav en móvil (el wall no tapa el input, no necesita ocultarlo)
  var bnav = document.getElementById('bottom-nav');
  if (bnav && window.innerWidth < 1025) bnav.style.display = 'flex';

  // Rellenar contenido del wall
  var elImg   = document.getElementById('vk-ai-wall-img');
  var elName  = document.getElementById('vk-ai-wall-name');
  var elDesc  = document.getElementById('vk-ai-wall-desc');
  var elPrice = document.getElementById('vk-ai-wall-price');
  var elCta   = document.getElementById('vk-ai-wall-cta');

  if (elImg)   elImg.innerHTML = img
    ? '<img src="'+escHtml(img)+'" alt="" style="width:100%;display:block;object-fit:cover;object-position:center top;">'
      + '<div style="position:absolute;inset:0;background:linear-gradient(to bottom,rgba(18,6,26,0) 55%,rgba(18,6,26,.92) 100%);pointer-events:none;"></div>'
    : '<div style="height:160px;display:flex;align-items:center;justify-content:center;font-size:5rem;line-height:1">&#x1F916;</div>';
  if (elName)  elName.textContent = name;
  if (elDesc)  elDesc.textContent = desc;
  if (elPrice) elPrice.innerHTML  = price
    ? '<div style="background:linear-gradient(135deg,#c44d8a,#8b2458);color:#fff;border-radius:20px;padding:.3rem 1.2rem;font-size:1rem;font-weight:700;display:inline-block">$'+escHtml(price)+'/un pago </div>'
    : '';
  if (elCta) {
    if (payUrl) {
      elCta.innerHTML = '<a href="'+escHtml(payUrl)+'" target="_blank" rel="noopener" style="display:block;background:linear-gradient(135deg,#c44d8a,#8b2458);color:#fff;border-radius:14px;padding:.9rem 1.5rem;font-size:.95rem;font-weight:700;text-decoration:none;text-align:center;box-shadow:0 6px 18px rgba(196,77,138,.45)">&#x1F680; Obtener Acceso</a>';
    } else if (contactUrl) {
      elCta.innerHTML = '<a href="'+escHtml(contactUrl)+'" target="_blank" rel="noopener" style="display:block;background:rgba(255,255,255,.1);border:1.5px solid rgba(255,255,255,.25);color:#fff;border-radius:14px;padding:.9rem 1.5rem;font-size:.95rem;font-weight:600;text-decoration:none;text-align:center;">&#x1F4AC; Solicitar acceso</a>';
    } else {
      elCta.innerHTML = '<p style="color:rgba(255,255,255,.5);font-size:.85rem;text-align:center;line-height:1.5">Para obtener acceso al Asistente VK AI,<br>escríbenos a nuestro equipo de soporte.</p>';
    }
  }

  // Mostrar wall encima del chat (ambos position:absolute;inset:0)
  var wall = document.getElementById('vk-ai-wall');
  if (wall) wall.style.display = 'flex';
  window._vkcWallVisible = true;
}


async function registerPlayerWithBackend(retryCount) {
  retryCount = retryCount || 0;
  try {
    if (!ST.token) return;
    if (!window.OneSignal || !_osReady) {
      if (retryCount < 5) setTimeout(function(){ registerPlayerWithBackend(retryCount+1); }, 2000);
      return;
    }

    var ps = OneSignal.User && OneSignal.User.PushSubscription;
    if (!ps) return;

    var optedIn     = ps.optedIn;
    var subscriptionId = ps.id || null;

    console.log('[VK Push] optedIn:', optedIn, '| id:', subscriptionId, '| permission:', Notification.permission);

    // Si no está suscrito activamente, forzar optIn
    if (!optedIn && Notification.permission === 'granted') {
      console.log('[VK Push] Forzando optIn...');
      try {
        await OneSignal.User.PushSubscription.optIn();
        // Esperar que OneSignal procese
        await new Promise(function(r){ setTimeout(r, 3000); });
        subscriptionId = OneSignal.User.PushSubscription.id || null;
        optedIn        = OneSignal.User.PushSubscription.optedIn;
        console.log('[VK Push] Tras optIn — optedIn:', optedIn, '| id:', subscriptionId);
      } catch(e) {
        console.warn('[VK Push] optIn error:', e.message);
      }
    }

    if (!subscriptionId) {
      if (retryCount < 4) {
        console.log('[VK Push] Sin ID, reintentando en 3s... intento:', retryCount+1);
        setTimeout(function(){ registerPlayerWithBackend(retryCount+1); }, 3000);
      }
      return;
    }

    if (_playerIdSaved === subscriptionId) return;

    console.log('[VK Push] Guardando player_id en WP:', subscriptionId);
    var res  = await fetch(apiURL('/vk/v1/register-player'), {
      method:  'POST',
      headers: {'Content-Type':'application/json'},
      body:    JSON.stringify({vk_token: ST.token, player_id: subscriptionId})
    });
    var data = await res.json();
    if (data.success) {
      _playerIdSaved = subscriptionId;
      console.log('[VK Push] <i class="fa-solid fa-check"></i> player_id guardado:', subscriptionId);
      var banner = document.getElementById('notif-push-banner');
      if (banner) banner.style.display = 'none';
      if (data.is_new) console.log('[VK Push] <i class="fa-solid fa-check"></i> Primera vez — bienvenida enviada');
    }
  } catch(e) {
    console.warn('[VK Push] registerPlayerWithBackend error:', e.message);
  }
}
;
var _currentPollId = 0;
var _pollVoted = {};

function escPoll(v){return String(v==null?'':v).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}
function pollImg(p){return p.featured_image_full||p.featured_image_large||p.featured_image||p.image||p.thumbnail||'';}

async function loadPolls(){
  var listEl=document.getElementById('polls-list');
  var sumEl=document.getElementById('polls-summary');
  if(!listEl)return;
  listEl.innerHTML='<div class="spinner-wrap"><div class="spinner"></div>Cargando encuestas...</div>';
  if(sumEl)sumEl.innerHTML=' Cargando...';
  try{
    var d=await getJSON(C.API_BASE + '/vk/v1/polls');
    var list=(d&&d.data)?d.data:[];
    if(sumEl)sumEl.innerHTML=' '+list.length+' encuesta'+(list.length===1?' disponible':'s disponibles');
    if(!list.length){
      listEl.innerHTML='<div class="poll-empty"><div class="poll-empty-icon"><i class="fas fa-pen-to-square"></i></div><h3 style="color:var(--vk-plum);font-size:1.35rem;margin-bottom:.35rem">No hay encuestas disponibles</h3><p>Cuando publiquemos nuevas encuestas aparecerán aquí.</p></div>';
      return;
    }
    listEl.innerHTML='<div class="polls-list">'+list.map(renderPollCard).join('')+'</div>';
  }catch(e){
    listEl.innerHTML='<div class="error-card"><h4>Error</h4><p>No se pudieron cargar las encuestas.</p></div>';
    if(sumEl)sumEl.innerHTML=' Error';
  }
}

function renderPollCard(p){
  var id=p.id||p.ID;
  var name=escPoll(p.name||p.title||'Encuesta');
  var desc=escPoll(p.description||'Participa y comparte tu opinion.');
  var status=p.status||'active';
  var active=(status==='active'||status==='published');
  var votes=Number(p.total_votes||0);
  var questions=Number(p.questions_count||p.total_questions||0);
  var voted=!!_pollVoted[id];
  var lname=(name||'').toLowerCase();
  var icon='fa-clipboard-list';
  if(lname.indexOf('satisfac')>=0||lname.indexOf('experienc')>=0) icon='fa-star';
  else if(lname.indexOf('tema')>=0||lname.indexOf('interes')>=0||lname.indexOf('nuevo')>=0) icon='fa-lightbulb';
  else if(lname.indexOf('plataform')>=0||lname.indexOf('mejora')>=0||lname.indexOf('feedback')>=0) icon='fa-cog';
  else if(lname.indexOf('evento')>=0||lname.indexOf('taller')>=0) icon='fa-calendar-alt';
  else if(lname.indexOf('curso')>=0||lname.indexOf('aprend')>=0) icon='fa-graduation-cap';
  var metaStr=questions?questions+' preguntas':(votes?votes+' respuestas':'');
  return '<article class="poll-row" onclick="openPoll('+id+')">'    +'<div class="poll-row-icon"><i class="fas '+icon+'"></i></div>'    +'<div class="poll-row-body">'    +'<h3 class="poll-row-title">'+name+'</h3>'    +'<p class="poll-row-desc">'+desc+'</p>'    +'</div>'    +(metaStr?'<span class="poll-row-meta">'+metaStr+'</span>':'')    +'<button class="poll-row-btn'+(voted?' poll-row-btn--done':'')+'" onclick="event.stopPropagation();openPoll('+id+')">'+(voted?'<i class="fa-solid fa-check"></i> Ya vote':'Responder')+'</button>'    +'</article>';
}

async function openPoll(id){
  _currentPollId=id;_pollAnswers={};
  goto('poll-detail');
  var short=document.getElementById('poll-title-short');
  if(short)short.textContent='Cargando...';
  var body=document.getElementById('poll-detail-body');
  if(body)body.innerHTML='<div class="spinner-wrap"><div class="spinner"></div>Cargando encuesta...</div>';
  try{
    var d=await getJSON(C.API_BASE + '/vk/v1/polls/' + id);
    if(!d||!(d.name||d.title)){
      body.innerHTML='<div class="error-card"><h4>Error</h4><p>No se pudo cargar la encuesta.</p></div>';return;
    }
    if(short)short.textContent=(d.name||d.title||'Encuesta').substring(0,22);
    renderPoll(d);
  }catch(e){
    body.innerHTML='<div class="error-card"><h4>Error</h4><p>No se pudo cargar la encuesta.</p></div>';
  }
}

function renderPoll(poll){
  var voted=!!_pollVoted[poll.id];
  var isActive=poll.status==='active'||poll.status==='published'||!poll.status;
  var questions=poll.questions||[];
  var name=escPoll(poll.name||poll.title||'Encuesta');
  var desc=escPoll(poll.description||'');
  var html='<div class="poll-detail-head"><h2>'+name+'</h2>'+(desc?'<p>'+desc+'</p>':'')+'<p> '+Number(poll.total_votes||0)+' respuestas totales</p></div>';
  if(!questions.length){
    html+='<div class="poll-empty"><div class="poll-empty-icon"></div><p>Esta encuesta no tiene preguntas disponibles.</p></div>';
    document.getElementById('poll-detail-body').innerHTML=html;return;
  }
  questions.forEach(function(q,qi){
    var qid=q.id||q.ID;
    var totalQ=q.total_votes||1;
    html+='<section class="poll-question"><p class="poll-question-title">'+(qi+1)+'. '+escPoll(q.text||q.title||'Pregunta')+'</p>';
    if(q.multiple)html+='<p class="poll-help">Puedes elegir varias opciones</p>';
    if(q.is_text){
      if(voted||!isActive){html+='<p style="font-size:.88rem;color:var(--ts);font-style:italic">Pregunta de texto libre</p>';}
      else{html+='<textarea class="poll-textarea" id="ptxt-'+qid+'" placeholder="Escribe tu respuesta..." onchange="_pollAnswers['+qid+']=[this.value]"></textarea>';}
      html+='</section>';return;
    }
    (q.options||[]).forEach(function(opt){
      var oid=opt.id||opt.ID;
      var pct=totalQ>0?Math.round(((opt.votes||0)/totalQ)*100):0;
      var selected=_pollAnswers[qid]&&_pollAnswers[qid].indexOf(oid)>=0;
      if(voted||!isActive){
        html+='<div class="poll-result"><div class="poll-result-row"><span>'+escPoll(opt.text||opt.title||'Opción')+'</span><span class="poll-result-pct">'+pct+'%</span></div><div class="poll-result-bar"><div class="poll-result-fill" style="width:'+pct+'%"></div></div><p class="poll-result-votes">'+Number(opt.votes||0)+' votos</p></div>';
      }else{
        html+='<div onclick="togglePollAnswer('+qid+','+oid+','+(q.multiple?1:0)+')" id="popt-'+qid+'-'+oid+'" class="poll-option '+(selected?'is-selected':'')+'"><div class="poll-box '+(q.multiple?'multi':'')+'">'+(selected?'<i class="fa-solid fa-check"></i>':'')+'</div><span>'+escPoll(opt.text||opt.title||'Opción')+'</span></div>';
      }
    });
    html+='</section>';
  });
  if(!voted&&isActive)html+='<div id="poll-submit-wrap" style="margin-top:.5rem"><div id="poll-error-msg" style="display:none" class="vk-poll-msg vk-poll-msg--error"></div><button onclick="submitPoll('+poll.id+')" id="btn-submit-poll" class="btn btn-primary" style="width:100%">Enviar respuestas</button></div>';
  else if(!isActive)html+='<div class="poll-status closed">Encuesta cerrada</div>';
  else html+='<div class="vk-poll-msg vk-poll-msg--voted"> Ya has votado en esta encuesta. ¡Gracias por tu participación!</div>';
  document.getElementById('poll-detail-body').innerHTML=html;
}

function togglePollAnswer(qId,optId,multiple){
  if(!_pollAnswers[qId])_pollAnswers[qId]=[];
  var idx=_pollAnswers[qId].indexOf(optId);
  if(multiple){if(idx>=0)_pollAnswers[qId].splice(idx,1);else _pollAnswers[qId].push(optId);}else{_pollAnswers[qId]=[optId];}
  var all=document.querySelectorAll('[id^="popt-'+qId+'-"]');
  all.forEach(function(el){
    var parts=el.id.split('-');var oid=parseInt(parts[parts.length-1],10);
    var sel=_pollAnswers[qId].indexOf(oid)>=0;
    el.classList.toggle('is-selected',sel);
    var box=el.querySelector('.poll-box');if(box)box.innerHTML=sel?'<i class="fa-solid fa-check"></i>':'';
  });
}

async function submitPoll(pollId){
  if(Object.keys(_pollAnswers).length===0){
    var errEl=document.getElementById('poll-error-msg');
    if(errEl){errEl.textContent=' Por favor selecciona al menos una respuesta.';errEl.style.display='flex';}
    else toast('Selecciona al menos una respuesta');
    return;
  }
  var errEl=document.getElementById('poll-error-msg');
  if(errEl)errEl.style.display='none';
  var answers=Object.keys(_pollAnswers).map(function(qId){return {question_id:parseInt(qId,10),answer_ids:_pollAnswers[qId]};});
  var body={answers:answers};
  var tok=ST.token||S.get('vk_token')||'';if(tok)body.vk_token=tok;
  var btn=document.getElementById('btn-submit-poll');
  if(btn){btn.disabled=true;btn.textContent='Enviando...';btn.style.opacity='.7';}
  try{
    var r=await fetch(C.API_BASE + '/vk/v1/polls/' + pollId + '/vote',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
    var d=await r.json();
    if(r.ok&&d.success){
      _pollVoted[pollId]=true;
      document.getElementById('poll-detail-body').innerHTML='<div class="vk-poll-success"><div class="vk-poll-success-icon"><i class="fa-solid fa-check"></i></div><h3>¡Voto registrado!</h3><p>Tu respuesta fue enviada correctamente. ¡Gracias por participar!</p><button onclick="goto(\'polls\')" class="btn btn-primary" style="margin-top:1.25rem;max-width:240px">Ver más encuestas</button></div>';
    }
    else if(d&&d.code==='already_voted'){
      _pollVoted[pollId]=true;
      document.getElementById('poll-detail-body').innerHTML='<div class="vk-poll-success"><div class="vk-poll-success-icon" style="font-size:2.5rem"> </div><h3>Ya has votado</h3><p>Ya registraste tu respuesta en esta encuesta anteriormente.</p><button onclick="goto(\'polls\')" class="btn btn-outline" style="margin-top:1.25rem;max-width:240px">Ver encuestas</button></div>';
    }
    else{if(btn){btn.disabled=false;btn.textContent='Enviar respuestas';btn.style.opacity='';}var e2=document.getElementById('poll-error-msg');if(e2){e2.textContent=' '+((d&&d.message)||'Error al enviar. Intenta de nuevo.');e2.style.display='flex';}else toast((d&&d.message)||'Error al enviar');}
  }catch(e){
    if(btn){btn.disabled=false;btn.textContent='Enviar respuestas';btn.style.opacity='';}
    var e3=document.getElementById('poll-error-msg');
    if(e3){e3.textContent=' Error de conexión. Verifica tu internet.';e3.style.display='flex';}else toast('Error de conexión');
  }
}


/*  RESPONSIVE CHROME PATCH  */
/* =========================================================
   RESPONSIVE CHROME PATCH FINAL
   Mantiene sidebar solo en escritorio y bottom nav solo en móvil.
   ========================================================= */
(function(){
  function m3cApplyChrome(){
    var logged=document.body.classList.contains('is-logged-in');
    var desktop=window.matchMedia('(min-width:1023px)').matches;
    var sidebar=document.getElementById('desktop-sidebar');
    var bottom=document.getElementById('bottom-nav');
    if(sidebar) sidebar.style.display=(logged&&desktop)?'flex':'none';
    if(bottom) bottom.style.display=(logged&&!desktop)?'flex':'none';
    if(desktop){
      var c=document.getElementById('course-cat-panel'), cb=document.getElementById('course-cat-backdrop');
      var p=document.getElementById('product-cat-panel'), pb=document.getElementById('product-cat-backdrop');
      if(c)c.classList.remove('open'); if(cb)cb.classList.remove('open');
      if(p)p.classList.remove('open'); if(pb)pb.classList.remove('open');
    }
  }
  window.addEventListener('resize',m3cApplyChrome);
  window.addEventListener('orientationchange',m3cApplyChrome);
  document.addEventListener('DOMContentLoaded',m3cApplyChrome);
  var oldEnter=window.enterApp;
  if(typeof oldEnter==='function'){
    window.enterApp=function(){ oldEnter.apply(this,arguments); setTimeout(m3cApplyChrome,0); };
  }
  var oldLogout=window.logout;
  if(typeof oldLogout==='function'){
    window.logout=function(){ oldLogout.apply(this,arguments); setTimeout(m3cApplyChrome,0); };
  }
  window.m3cApplyChrome=m3cApplyChrome;
})();


/* ═══════════════════════════════════════════════════════════
   HELPERS GLOBALES DE ESCAPE / MIME
═══════════════════════════════════════════════════════════ */
function _esc(v){
  return String(v==null?'':v).replace(/[&<>"']/g,function(c){
    return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
  });
}
function _escHtml(v){ return _esc(v); }
function _mimeExt(mime){
  if(!mime) return '';
  var map={'application/pdf':'PDF','application/msword':'DOC',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document':'DOCX',
    'application/vnd.ms-excel':'XLS',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':'XLSX',
    'application/vnd.ms-powerpoint':'PPT',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation':'PPTX',
    'application/zip':'ZIP','application/x-zip-compressed':'ZIP',
    'text/plain':'TXT','text/csv':'CSV'};
  if(map[mime]) return map[mime];
  if(mime.indexOf('image/')===0) return mime.split('/')[1].toUpperCase().substring(0,4);
  if(mime.indexOf('video/')===0) return mime.split('/')[1].toUpperCase().substring(0,4);
  if(mime.indexOf('audio/')===0) return mime.split('/')[1].toUpperCase().substring(0,4);
  return '';
}

/* ═══════════════════════════════════════════════════════════
   MODAL VISOR DE ARCHIVOS
═══════════════════════════════════════════════════════════ */
function vkOpenFile(url, title, mime){
  var modal=document.getElementById('vk-file-modal');
  var body=document.getElementById('vk-file-modal-body');
  var titleEl=document.getElementById('vk-file-modal-title');
  var dlBtn=document.getElementById('vk-file-modal-dl');
  if(!modal||!body) return;

  titleEl.textContent=title||url.split('/').pop();
  dlBtn.href=url;
  dlBtn.download=title||'';
  body.innerHTML='';
  modal.classList.add('vk-open');
  document.body.style.overflow='hidden';

  var isIOS=/iphone|ipad|ipod/i.test(navigator.userAgent);
  var isSafari=/^((?!chrome|android).)*safari/i.test(navigator.userAgent);
  mime=mime||'';

  if(mime==='application/pdf'){
    var src=isIOS||isSafari
      ? 'https://docs.google.com/viewer?embedded=true&url='+encodeURIComponent(url)
      : url+'#view=FitH&toolbar=0';
    body.innerHTML='<iframe src="'+src+'" title="'+_esc(title)+'"></iframe>';

  } else if(mime.indexOf('image/')===0){
    body.innerHTML='<img src="'+url+'" alt="'+_esc(title)+'">';

  } else if(mime.indexOf('video/')===0){
    body.innerHTML='<video controls autoplay playsinline src="'+url+'"></video>';

  } else if(mime.indexOf('audio/')===0){
    body.innerHTML='<audio controls autoplay src="'+url+'" style="width:100%;padding:2rem;box-sizing:border-box"></audio>';

  } else if(mime==='text/plain'||mime==='text/csv'){
    body.innerHTML='<iframe src="'+url+'" title="'+_esc(title)+'"></iframe>';

  } else {
    // Tipo no previsualizable → mostrar botón de descarga/abrir
    var ext=_mimeExt(mime)||mime.split('/').pop().toUpperCase();
    body.innerHTML='<div class="vk-file-modal-unsupported">'
      +_attachIcon(mime).replace('1.15rem','3.5rem')
      +'<p>No es posible previsualizar este archivo<br><strong>'+_escHtml(title)+'</strong>'
      +(ext?' <span class="cd-attach-badge">'+ext+'</span>':'')+'</p>'
      +'<a href="'+url+'" target="_blank" rel="noopener"><i class="fas fa-download"></i> Descargar archivo</a>'
      +'</div>';
  }
}

function vkCloseFileModal(){
  var modal=document.getElementById('vk-file-modal');
  if(!modal) return;
  modal.classList.remove('vk-open');
  document.body.style.overflow='';
  // Detener media al cerrar
  var body=document.getElementById('vk-file-modal-body');
  if(body) body.innerHTML='';
}

function vkFileModalBgClick(e){
  if(e.target===document.getElementById('vk-file-modal')) vkCloseFileModal();
}

// Cerrar con tecla Escape
document.addEventListener('keydown',function(e){
  if(e.key==='Escape') vkCloseFileModal();
});

/* ═══════════════════════════════════════════════════════════
   RECUPERAR CONTRASEÑA
═══════════════════════════════════════════════════════════ */
function showForgotPasswordModal(){
  var m=document.getElementById('modal-forgot-password');
  if(!m)return;
  m.style.display='flex';
  var inp=document.getElementById('forgot-email');
  if(inp){inp.value='';inp.focus();}
  var msg=document.getElementById('forgot-msg');
  if(msg){msg.textContent='';msg.style.color='';}
  var btn=document.getElementById('btn-forgot');
  if(btn){btn.disabled=false;btn.textContent='Enviar enlace';}
}

function closeForgotPasswordModal(){
  var m=document.getElementById('modal-forgot-password');
  if(m)m.style.display='none';
}

async function sendForgotPassword(){
  var email=(document.getElementById('forgot-email')||{}).value||'';
  var msg=document.getElementById('forgot-msg');
  var btn=document.getElementById('btn-forgot');
  if(!email||!email.includes('@')){
    if(msg){msg.textContent='Ingresa un correo válido.';msg.style.color='#c62828';}
    return;
  }
  if(btn){btn.disabled=true;btn.textContent='Enviando...';}
  if(msg){msg.textContent='';msg.style.color='';}
  try{
    var r=await fetch(C.API_BASE+'/vk/v1/forgot-password',{
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({email:email})
    });
    var d=await r.json();
    if(msg){
      msg.textContent=d.message||'Si existe esa cuenta, recibirás instrucciones.';
      msg.style.color='#2e7d32';
    }
    if(btn){btn.textContent='Correo enviado ✓';}
  }catch(e){
    if(msg){msg.textContent='Error de conexión. Intenta de nuevo.';msg.style.color='#c62828';}
    if(btn){btn.disabled=false;btn.textContent='Enviar enlace';}
  }
}

/* ═══════════════════════════════════════════════════════════
   RESTABLECER CONTRASEÑA (desde enlace en el correo)
═══════════════════════════════════════════════════════════ */
(function checkResetParams(){
  var p=new URLSearchParams(window.location.search);
  var key=p.get('reset_key');
  var login=p.get('reset_login');
  if(!key||!login)return;
  sessionStorage.setItem('vk_rk',key);
  sessionStorage.setItem('vk_rl',login);
  history.replaceState(null,'',window.location.pathname);
  var s=document.getElementById('screen-reset-password');
  if(s)s.style.display='flex';
})();

async function doResetPassword(){
  var p1=(document.getElementById('reset-pass1')||{}).value||'';
  var p2=(document.getElementById('reset-pass2')||{}).value||'';
  var msg=document.getElementById('reset-pass-msg');
  var btn=document.getElementById('btn-reset-pass');
  var key=sessionStorage.getItem('vk_rk')||'';
  var login=sessionStorage.getItem('vk_rl')||'';
  if(!key||!login){
    if(msg){msg.textContent='Enlace inválido. Solicita uno nuevo.';msg.style.color='#c62828';}
    return;
  }
  if(p1.length<8){
    if(msg){msg.textContent='La contraseña debe tener al menos 8 caracteres.';msg.style.color='#c62828';}
    return;
  }
  if(p1!==p2){
    if(msg){msg.textContent='Las contraseñas no coinciden.';msg.style.color='#c62828';}
    return;
  }
  if(btn){btn.disabled=true;btn.textContent='Guardando...';}
  if(msg)msg.textContent='';
  try{
    var r=await fetch(C.API_BASE+'/vk/v1/reset-password',{
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({key:key,login:login,password:p1})
    });
    var d=await r.json();
    if(r.ok&&d.success){
      sessionStorage.removeItem('vk_rk');
      sessionStorage.removeItem('vk_rl');
      if(msg){msg.textContent='¡Contraseña actualizada! Ya puedes iniciar sesión.';msg.style.color='#2e7d32';}
      if(btn){btn.textContent='¡Listo! ✓';}
      setTimeout(function(){
        var s=document.getElementById('screen-reset-password');
        if(s)s.style.display='none';
        goto('login');
      },2200);
    }else{
      if(msg){msg.textContent=(d.message||d.data&&d.data.message||'Error. El enlace puede haber expirado.');msg.style.color='#c62828';}
      if(btn){btn.disabled=false;btn.textContent='Guardar nueva contraseña';}
    }
  }catch(e){
    if(msg){msg.textContent='Error de conexión. Intenta de nuevo.';msg.style.color='#c62828';}
    if(btn){btn.disabled=false;btn.textContent='Guardar nueva contraseña';}
  }
}

/* ══════════════════════════════════════════════════════════════════
   DOCUMENTOS
══════════════════════════════════════════════════════════════════ */
var _docsAll = [];
var _docsActiveCat = '';
async function loadDocuments(){
  var el = document.getElementById('docs-list');
  if(!el) return;
  el.innerHTML = '<div class="spinner-wrap"><div class="spinner"></div>Cargando biblioteca...</div>';
  try{
    var d = await getJSON(C.API_BASE + '/vk/v1/documents');
    _docsAll = (d && d.data) ? d.data : [];
    _buildDocCategories();
    _renderDocs(_docsAll);
  }catch(e){
    el.innerHTML = '<div style="text-align:center;padding:3rem 1rem;color:var(--ts)"><i class="fas fa-triangle-exclamation" style="font-size:2rem;margin-bottom:.75rem;display:block"></i><p>Error al cargar documentos</p><button class="btn btn-sm btn-outline" onclick="loadDocuments()" style="margin-top:.75rem">Reintentar</button></div>';
  }
}

function _buildDocCategories(){
  var catsEl = document.getElementById('docs-categories');
  if(!catsEl) return;
  // Recolectar categorías únicas
  var seen = {}, cats = [];
  _docsAll.forEach(function(d){
    (d.categories||[]).forEach(function(c){
      if(!seen[c.id]){ seen[c.id]=true; cats.push(c); }
    });
  });
  if(!cats.length){ catsEl.style.display='none'; return; }
  catsEl.style.display = 'flex';
  var html = '<button class="doc-cat-pill'+(!'_docsActiveCat'?' active':'')+'" onclick="filterByDocCat(\'\')" style="white-space:nowrap">Todos</button>';
  cats.forEach(function(c){
    html += '<button class="doc-cat-pill" onclick="filterByDocCat(\''+escHTML(c.slug)+'\')" data-cat="'+escHTML(c.slug)+'" style="white-space:nowrap">'+escHTML(c.name)+'</button>';
  });
  catsEl.innerHTML = html;
}

function filterByDocCat(slug){
  _docsActiveCat = slug;
  document.querySelectorAll('.doc-cat-pill').forEach(function(el){
    el.classList.toggle('active', el.dataset.cat === slug || (!slug && !el.dataset.cat));
  });
  var query = (document.getElementById('docs-search')||{}).value || '';
  filterDocuments(query);
}

function filterDocuments(query){
  var q = (query||'').toLowerCase().trim();
  var filtered = _docsAll.filter(function(d){
    var matchCat = !_docsActiveCat || (d.categories||[]).some(function(c){ return c.slug===_docsActiveCat; });
    var matchQ   = !q || d.title.toLowerCase().indexOf(q)>-1 || (d.description||'').toLowerCase().indexOf(q)>-1;
    return matchCat && matchQ;
  });
  _renderDocs(filtered);
}

/* Icono por extensión — color + clase FontAwesome */
var _DOC_ICON_MAP = {
  // Documentos
  pdf:  { cls:'fas fa-file-pdf',         color:'#e53935' },
  doc:  { cls:'fas fa-file-word',         color:'#1565c0' },
  docx: { cls:'fas fa-file-word',         color:'#1565c0' },
  xls:  { cls:'fas fa-file-excel',        color:'#2e7d32' },
  xlsx: { cls:'fas fa-file-excel',        color:'#2e7d32' },
  ppt:  { cls:'fas fa-file-powerpoint',   color:'#e65100' },
  pptx: { cls:'fas fa-file-powerpoint',   color:'#e65100' },
  // Comprimidos
  zip:  { cls:'fas fa-file-zipper',       color:'#6a1b9a' },
  rar:  { cls:'fas fa-file-zipper',       color:'#6a1b9a' },
  '7z': { cls:'fas fa-file-zipper',       color:'#6a1b9a' },
  // Video
  mp4:  { cls:'fas fa-file-video',        color:'#00695c' },
  mov:  { cls:'fas fa-file-video',        color:'#00695c' },
  avi:  { cls:'fas fa-file-video',        color:'#00695c' },
  mkv:  { cls:'fas fa-file-video',        color:'#00695c' },
  webm: { cls:'fas fa-file-video',        color:'#00695c' },
  // Audio
  mp3:  { cls:'fas fa-file-audio',        color:'#7b1fa2' },
  wav:  { cls:'fas fa-file-audio',        color:'#7b1fa2' },
  ogg:  { cls:'fas fa-file-audio',        color:'#7b1fa2' },
  m4a:  { cls:'fas fa-file-audio',        color:'#7b1fa2' },
  // Imágenes
  jpg:  { cls:'fas fa-file-image',        color:'#c44d8a' },
  jpeg: { cls:'fas fa-file-image',        color:'#c44d8a' },
  png:  { cls:'fas fa-file-image',        color:'#c44d8a' },
  gif:  { cls:'fas fa-file-image',        color:'#c44d8a' },
  svg:  { cls:'fas fa-file-image',        color:'#c44d8a' },
  webp: { cls:'fas fa-file-image',        color:'#c44d8a' },
  // Texto / código
  txt:  { cls:'fas fa-file-lines',        color:'#546e7a' },
  csv:  { cls:'fas fa-file-csv',          color:'#2e7d32' },
};
var _DOC_EXT_COLORS = {};
(function(){
  Object.keys(_DOC_ICON_MAP).forEach(function(k){ _DOC_EXT_COLORS[k] = _DOC_ICON_MAP[k].color; });
})();

function _docIcon(ext){
  var e = (ext||'').toLowerCase();
  var m = _DOC_ICON_MAP[e];
  var cls   = m ? m.cls   : 'fas fa-file';
  var color = m ? m.color : 'var(--vk-rose)';
  return '<div style="width:48px;height:48px;border-radius:12px;background:'+color+'18;display:flex;align-items:center;justify-content:center;flex-shrink:0">'
    +'<i class="'+cls+'" style="color:'+color+';font-size:1.45rem"></i>'
  +'</div>';
}

function _renderDocs(list){
  var el = document.getElementById('docs-list');
  if(!el) return;
  if(!list.length){
    el.innerHTML = '<div style="text-align:center;padding:3rem 1rem">'
      +'<i class="fas fa-folder-open" style="font-size:2.5rem;color:var(--tu);margin-bottom:.75rem;display:block"></i>'
      +'<p style="color:var(--ts);font-size:.9rem">No se encontraron archivos en la biblioteca</p>'
    +'</div>';
    return;
  }

  /* ── Layout de tarjetas — funciona en móvil y escritorio ── */
  // Log para verificar datos de la API
  if(list.length) console.log('[docs] primer doc:', JSON.stringify({id:list[0].id, file_ext:list[0].file_ext, real_filename:list[0].real_filename, file_url:list[0].file_url}));

  var cards = list.map(function(d){
    // Extensión: 1) file_ext del API  2) real_filename  3) file_url
    var ext = (d.file_ext||'').toLowerCase();
    if(!ext && d.real_filename){
      var _rfDot = d.real_filename.lastIndexOf('.');
      if(_rfDot > -1) ext = d.real_filename.slice(_rfDot+1).toLowerCase();
    }
    if(!ext && d.file_url){
      var _urlPath = d.file_url.split('?')[0];
      var _dotIdx  = _urlPath.lastIndexOf('.');
      if(_dotIdx > -1 && _dotIdx > _urlPath.lastIndexOf('/')) ext = _urlPath.slice(_dotIdx+1).toLowerCase();
    }
    // Limitar a extensiones conocidas (evitar falsos como "com" de dominios)
    if(ext && !_DOC_ICON_MAP[ext] && ext.length > 5) ext = '';
    var m    = _DOC_ICON_MAP[ext];
    var color = m ? m.color : 'var(--vk-rose)';

    var cats = (d.categories||[]).map(function(c){
      return '<span style="font-size:.68rem;background:'+color+'18;color:'+color+';border-radius:4px;padding:.1rem .4rem;white-space:nowrap">'+escHTML(c.name)+'</span>';
    }).join(' ');

    var extBadge = ext
      ? '<span style="font-size:.62rem;font-weight:700;background:'+color+';color:#fff;border-radius:4px;padding:.1rem .4rem;text-transform:uppercase;letter-spacing:.04em">'+escHTML(ext)+'</span>'
      : '';

    var meta = [];
    if(d.file_size) meta.push('<span><i class="fas fa-weight-hanging" style="opacity:.5;margin-right:.2rem"></i>'+escHTML(d.file_size)+'</span>');
    if(d.downloads) meta.push('<span><i class="fas fa-download" style="opacity:.5;margin-right:.2rem"></i>'+d.downloads+'</span>');
    if(d.date)      meta.push('<span>'+escHTML(d.date)+'</span>');

    var dlId   = d.id;
    var dlRaw  = d.file_url || d.post_url || '';
    // Nombre: usar el título legible + extensión extraída, O el nombre real del archivo
    var _baseName = (d.title || 'archivo').replace(/[\/\\:*?"<>|]/g,'_');
    var dlName = _baseName + (ext ? '.' + ext : '');
    var dlCall = 'downloadDoc('+dlId+',\''+escJS(dlRaw)+'\',\''+escJS(dlName)+'\')';

    return '<div class="doc-card" id="doc-card-'+dlId+'" onclick="'+dlCall+'" role="button" tabindex="0" style="display:flex;align-items:flex-start;gap:.85rem;padding:.9rem 1rem;border-radius:14px;background:var(--card-bg,rgba(255,255,255,.04));border:1px solid rgba(255,255,255,.06);margin-bottom:.6rem;cursor:pointer;transition:background .15s" onmouseover="this.style.background=\'rgba(255,255,255,.08)\'" onmouseout="this.style.background=\'var(--card-bg,rgba(255,255,255,.04))\'">'
      // Icono tipo archivo
      +_docIcon(ext)
      // Contenido central
      +'<div style="flex:1;min-width:0">'
        +'<div style="display:flex;align-items:center;gap:.4rem;flex-wrap:wrap;margin-bottom:.15rem">'
          +'<span style="font-size:.88rem;font-weight:700;color:var(--td);line-height:1.3;word-break:break-word">'+escHTML(d.title)+'</span>'
          +extBadge
        +'</div>'
        +(d.description ? '<div style="font-size:.75rem;color:var(--ts);line-height:1.4;margin-bottom:.35rem;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical">'+escHTML(d.description)+'</div>' : '')
        +(cats ? '<div style="display:flex;gap:.3rem;flex-wrap:wrap;margin-bottom:.35rem">'+cats+'</div>' : '')
        +(meta.length ? '<div style="display:flex;gap:.75rem;flex-wrap:wrap;font-size:.72rem;color:var(--tu)">'+meta.join('')+'</div>' : '')
        // Barra de progreso (oculta por defecto)
        +'<div id="doc-prog-'+dlId+'" style="display:none;margin-top:.5rem">'
          +'<div style="height:3px;border-radius:2px;background:rgba(255,255,255,.1);overflow:hidden">'
            +'<div id="doc-prog-bar-'+dlId+'" style="height:100%;width:0%;background:'+color+';transition:width .2s;border-radius:2px"></div>'
          +'</div>'
          +'<div id="doc-prog-txt-'+dlId+'" style="font-size:.68rem;color:var(--ts);margin-top:.2rem">Descargando...</div>'
        +'</div>'
      +'</div>'
      // Botón descarga con icono del tipo
      +'<button onclick="event.stopPropagation();'+dlCall+'" id="doc-btn-'+dlId+'" title="Descargar '+escHTML(d.title)+'" style="flex-shrink:0;background:'+color+';color:#fff;border:none;border-radius:10px;width:40px;height:40px;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:.95rem;margin-top:.1rem;transition:opacity .15s">'
        +'<i class="fas fa-download"></i>'
      +'</button>'
    +'</div>';
  }).join('');

  el.innerHTML = '<div style="padding:.25rem 0">'+cards+'</div>';
}

function downloadDoc(id, url, filename){
  if(!id) return;
  var token = ST && ST.token ? ST.token : '';

  // Mostrar feedback visual
  var btn = document.getElementById('doc-btn-'+id);
  var txt = document.getElementById('doc-prog-txt-'+id);
  var prog = document.getElementById('doc-prog-'+id);
  if(btn){ btn.style.opacity='.5'; btn.disabled=true; }
  if(prog) prog.style.display='block';
  if(txt)  txt.textContent='Descargando...';

  // Endpoint de descarga dentro de WordPress: accede al archivo por filesystem,
  // evita loopback/proxy entre subdominios (problema en SiteGround).
  var dlUrl = C.API_BASE + '/vk/v1/documents/file'
    + '?id='    + encodeURIComponent(id)
    + '&name='  + encodeURIComponent(filename || 'archivo')
    + '&token=' + encodeURIComponent(token);

  var a = document.createElement('a');
  a.href     = dlUrl;
  a.download = filename || 'archivo';
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);

  // Restaurar botón tras 3s
  setTimeout(function(){
    if(btn){ btn.style.opacity='1'; btn.disabled=false; }
    if(prog) prog.style.display='none';
  }, 3000);
}

function _fmtBytes(b){
  if(b < 1024) return b+' B';
  if(b < 1048576) return (b/1024).toFixed(1)+' KB';
  return (b/1048576).toFixed(1)+' MB';
}

/*  PAQUETES (legacy — eliminado duplicado, ver loadBundles() arriba) */

/* ══════════════════════════════════════════════════════════════════
   MI DIRECTORIO
══════════════════════════════════════════════════════════════════ */
var _dirProfile    = null;
var _dirCategories = [];
var _dirSaving     = false;
async function _dirPrefetchStatus(){
  try{
    var s = await getJSON(apiURL('/vk/v1/dir/status'));
    if(s && s.ok) _dirSetSubtitle(s.has_profile);
  }catch(e){ /* silencioso */ }
}

function _dirSetSubtitle(hasProfile){
  var el = document.querySelector('#screen-directory-profile .desktop-page-sub');
  if(el) el.textContent = hasProfile
    ? 'Editando tu perfil profesional'
    : 'Crea tu perfil en el directorio';
}

async function loadDirectoryProfile(){
  var wrap = document.getElementById('dir-form-wrap');
  if(!wrap) return;
  if(_dirProfile) _dirSetSubtitle(true);
  wrap.innerHTML = '<div class="spinner-wrap"><div class="spinner"></div>Cargando perfil...</div>';
  try{
    var [rp, rc] = await Promise.all([
      getJSON(apiURL('/vk/v1/dir/profile')),
      getJSON(apiURL('/vk/v1/dir/categories')),
    ]);
    _dirProfile    = (rp && rp.ok && rp.profile) ? rp.profile : null;
    _dirCategories = (rc && rc.ok && rc.categories) ? rc.categories : [];
    _dirSetSubtitle(!!_dirProfile);
    _renderDirForm();
  }catch(e){
    console.error('[Dir] loadDirectoryProfile error:', e);
    wrap.innerHTML = '<div style="text-align:center;padding:2rem;color:var(--ts)">'
      +'<i class="fas fa-triangle-exclamation" style="font-size:2rem;margin-bottom:.75rem;display:block"></i>'
      +'<p>Error al cargar el perfil del directorio.</p>'
      +'<button class="btn btn-outline btn-sm" onclick="loadDirectoryProfile()" style="margin-top:.75rem">Reintentar</button>'
      +'</div>';
  }
}

/* Helpers de renderizado ──────────────────────────────────────────────────── */

function _dirField(id, label, type, val, placeholder, hint){
  val = escHTML(val||'');
  return '<div style="margin-bottom:1rem">'
    +'<label style="display:block;font-size:.78rem;font-weight:600;color:var(--tm);margin-bottom:.3rem">'+label+'</label>'
    +(type==='textarea'
      ? '<textarea id="'+id+'" placeholder="'+escHTML(placeholder||'')+'" rows="3" style="width:100%;background:var(--card);border:1px solid rgba(255,255,255,.08);color:var(--td);border-radius:10px;padding:.65rem .75rem;font-size:.87rem;outline:none;resize:vertical;box-sizing:border-box;font-family:inherit">'+val+'</textarea>'
      : '<input id="'+id+'" type="'+type+'" value="'+val+'" placeholder="'+escHTML(placeholder||'')+'" style="width:100%;background:var(--card);border:1px solid rgba(255,255,255,.08);color:var(--td);border-radius:10px;padding:.65rem .75rem;font-size:.87rem;outline:none;box-sizing:border-box">'
    )
    +(hint ? '<p style="font-size:.72rem;color:var(--tu);margin:.25rem 0 0">'+hint+'</p>' : '')
    +'</div>';
}

function _dirSection(title, icon, content){
  return '<div style="background:var(--card);border:1px solid rgba(255,255,255,.06);border-radius:14px;padding:1rem 1.1rem;margin-bottom:1rem">'
    +'<p style="font-size:.8rem;font-weight:700;color:var(--vk-rose);margin:0 0 .85rem;display:flex;align-items:center;gap:.4rem"><i class="fas '+icon+'"></i> '+title+'</p>'
    +content
    +'</div>';
}

function _dirImgBlock(imgUrl, fieldId, label, existingId){
  existingId = existingId || '';
  return '<div style="margin-bottom:.75rem">'
    +'<label style="display:block;font-size:.78rem;font-weight:600;color:var(--tm);margin-bottom:.4rem">'+label+'</label>'
    +'<div style="display:flex;align-items:center;gap:.75rem">'
    +(imgUrl
      ? '<img src="'+escHTML(imgUrl)+'" id="'+fieldId+'-preview" style="width:56px;height:56px;border-radius:10px;object-fit:cover;flex-shrink:0">'
      : '<div id="'+fieldId+'-preview" style="width:56px;height:56px;border-radius:10px;background:var(--vk-petal);display:flex;align-items:center;justify-content:center;flex-shrink:0"><i class="fas fa-image" style="color:var(--tu);font-size:1.3rem"></i></div>'
    )
    +'<label style="cursor:pointer;flex:1">'
    +'<span class="btn btn-outline btn-sm" style="pointer-events:none" id="'+fieldId+'-lbl">'
    +'<i class="fas fa-arrow-up-from-bracket"></i> '+(imgUrl?'Cambiar':'Subir imagen')+'</span>'
    +'<input type="file" id="'+fieldId+'" accept="image/jpeg,image/jpg,image/png,image/tiff" style="display:none" onchange="uploadDirImage(this,\''+fieldId+'\')">'
    +'</label>'
    +'</div>'
    +'<p style="font-size:.7rem;color:var(--tu);margin:.3rem 0 0;line-height:1.5">'
    +'<i class="fas fa-circle-info" style="margin-right:.2rem;color:var(--vk-rose)"></i>'
    +'Formatos: JPG, JPEG, PNG, TIFF · Máx. 5 MB · '
    +'Tamaño recomendado: <strong style="color:var(--tm)">800×800 px</strong> para foto de perfil, '
    +'<strong style="color:var(--tm)">400×200 px</strong> para logo'
    +'</p>'
    +'<input type="hidden" id="'+fieldId+'-id" value="'+escHTML(String(existingId))+'">'
    +'</div>';
}

function _renderDirForm(){
  var wrap = document.getElementById('dir-form-wrap');
  if(!wrap) return;
  var P = _dirProfile || {};

  var catIds = P.category_ids || [];

  var html = '';

  // ── Barra de estado ────────────────────────────────────────────────────────
  var approvalStatus = P.approval_status || 'pending';
  var isApproved = approvalStatus === 'approved';

  if(P.post_id){
    html += '<div style="margin-bottom:1rem;border-radius:14px;overflow:hidden;border:1px solid '
      +(isApproved ? 'rgba(0,200,232,.2)' : 'rgba(255,193,7,.35)')+'">';
    if(isApproved){
      html += '<div style="display:flex;align-items:center;justify-content:space-between;'
        +'padding:.65rem 1.1rem;background:rgba(0,200,232,.07);border-bottom:1px solid rgba(0,200,232,.12)">'
        +'<span style="font-size:.8rem;font-weight:700;color:var(--vk-rose);display:flex;align-items:center;gap:.4rem">'
        +'<i class="fas fa-circle-check"></i> Tu perfil está activo en el directorio</span>'
        +'<span style="font-size:.72rem;color:var(--tu);background:rgba(0,200,232,.1);padding:.15rem .55rem;border-radius:6px">'
        +'publicado</span>'
        +'</div>';
      if(P.permalink){
        html += '<a href="'+escHTML(P.permalink)+'" target="_blank" '
          +'style="display:flex;align-items:center;justify-content:space-between;padding:.75rem 1.1rem;'
          +'background:rgba(2,12,20,.6);text-decoration:none">'
          +'<span style="display:flex;align-items:center;gap:.5rem;font-size:.85rem;font-weight:600;color:var(--vk-rose)">'
          +'<i class="fas fa-globe"></i> Ver perfil en el directorio</span>'
          +'<i class="fas fa-arrow-up-right-from-square" style="color:var(--ts);font-size:.8rem"></i>'
          +'</a>';
      }
    } else if(approvalStatus === 'rejected'){
      html += '<div style="padding:.75rem 1.1rem;background:rgba(224,82,82,.08)">'
        +'<p style="margin:0 0 .25rem;font-size:.82rem;font-weight:700;color:#c0392b">⛔ Perfil no aprobado</p>'
        +'<p style="margin:0;font-size:.8rem;color:var(--tm)">Tu perfil fue revisado y no pudo ser aprobado. '
        +'Puedes editarlo y enviarlo nuevamente.</p>'
        +'</div>';
    } else {
      html += '<div style="padding:.75rem 1.1rem;background:rgba(255,193,7,.08)">'
        +'<p style="margin:0 0 .25rem;font-size:.82rem;font-weight:700;color:#856404">🕐 Pendiente de aprobación</p>'
        +'<p style="margin:0;font-size:.8rem;color:var(--tm)">Tu perfil fue recibido y está siendo revisado. '
        +'Aparecerá en el directorio una vez aprobado por un administrador.</p>'
        +'</div>';
    }
    html += '</div>';
  } else {
    html += '<div style="margin-bottom:1.25rem;padding:1rem 1.1rem;border-radius:14px;'
      +'background:rgba(0,200,232,.06);border:1px dashed rgba(0,200,232,.25)">'
      +'<p style="margin:0 0 .35rem;font-size:.88rem;font-weight:700;color:var(--vk-rose)">'
      +'<i class="fas fa-circle-plus"></i> Crea tu perfil profesional</p>'
      +'<p style="margin:0;font-size:.8rem;color:var(--ts)">Aparecerás en el directorio de Roca Terapeuta. '
      +'Solo puedes tener un perfil activo por cuenta.</p>'
      +'</div>';
  }

  // ── Secciones del formulario ───────────────────────────────────────────────
  html += '<div id="dir-sections-grid">';

  html += _dirSection('Información Básica', 'fa-user-tie',
    _dirImgBlock(P.featured_image, 'dp-img-featured', 'Foto de perfil',    P.featured_image_id)
   +_dirImgBlock(P.logo,           'dp-img-logo',     'Logo / imagen extra', P.logo_id)
   +_dirField('dp-name',    'Nombre completo / Nombre comercial', 'text',     P.name,    'Nombre de tu práctica profesional')
   +_dirField('dp-tagline', 'Tagline (descripción corta)',        'text',     P.tagline, 'Una línea que defina tu especialidad')
   +_dirField('dp-bio',     'Sobre ti (descripción completa)',    'textarea', P.bio,     'Formación, experiencia y enfoque terapéutico...',
      'Se mostrará en tu ficha pública en el directorio.')
  );

  html += _dirSection('Información Profesional', 'fa-briefcase-medical',
    _dirField('dp-profession', 'Profesión / Título',       'text',     P.profession, 'Psicólogo, Terapeuta, Coach...')
   +_dirField('dp-experience', 'Años de experiencia',       'number',   P.experience, '10')
   +_dirField('dp-price-range','Precio por sesión',         'text',     P.price_range,'Ej: $800 MXN')
   +_dirField('dp-services',   'Servicios ofrecidos',       'textarea', P.services,   'Ej: Consulta individual, Terapia de pareja, Talleres grupales',
      'Separa cada servicio con una coma (,). Ej: Consulta individual, Terapia de pareja, Talleres grupales')
   +'<div style="margin-bottom:1rem">'
   +'<label style="display:block;font-size:.78rem;font-weight:600;color:var(--tm);margin-bottom:.55rem">Especialidades</label>'
   +'<div id="dp-categories-wrap" style="display:flex;flex-wrap:wrap;gap:.45rem">'
   +(_dirCategories.length
     ? _dirCategories.map(function(c){
         var chk = catIds.indexOf(c.id)>=0 ? ' checked' : '';
         return '<label style="display:flex;align-items:center;gap:.3rem;padding:.35rem .75rem;border-radius:20px;'
           +'background:'+(catIds.indexOf(c.id)>=0?'rgba(0,200,232,.15)':'rgba(255,255,255,.05)')+';'
           +'border:1.5px solid '+(catIds.indexOf(c.id)>=0?'rgba(0,200,232,.5)':'rgba(255,255,255,.1)')+';'
           +'cursor:pointer;font-size:.8rem;color:var(--td);user-select:none" class="dp-cat-pill">'
           +'<input type="checkbox" value="'+c.id+'"'+chk+' style="display:none" data-catid="'+c.id+'">'
           +(c.icon?escHTML(c.icon)+' ':'')+escHTML(c.name)
           +'</label>';
       }).join('')
     : '<p style="font-size:.8rem;color:var(--tu);margin:0">Sin categorías disponibles. Créalas desde WordPress → Directorio → Categorías.</p>'
   )
   +'</div></div>'
   +(function(){
    var techOpts=['Casco de Estimulación Magnética Transcraneal PEMF','Colchoneta de Campos Magnéticos Pulsados','Equipo de Campos Magnéticos Pulsados con 2 bobinas'];
    var saved=(P.technologies||'');
    var html='<div style="margin-top:.65rem">'
      +'<label style="display:block;font-size:.78rem;font-weight:600;color:var(--tm);margin-bottom:.4rem">Tecnología / Equipamiento</label>'
      +'<div id="dp-tech-wrap" style="display:flex;flex-direction:column;gap:.15rem;padding:.5rem .65rem;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.1);border-radius:10px">';
    techOpts.forEach(function(opt){
      var chk=saved.indexOf(opt)>=0?' checked':'';
      html+='<label style="display:flex;align-items:flex-start;gap:.55rem;padding:.4rem .7rem;border-radius:10px;cursor:pointer">'
        +'<input type="checkbox" name="dp-tech" value="'+opt+'"'+chk+' style="margin-top:.25rem;accent-color:#00c8e8;cursor:pointer">'
        +'<span style="font-size:.84rem;color:var(--td);line-height:1.35">'+opt+'</span>'
        +'</label>';
    });
    html+='</div></div>';
    return html;
   }())
  );

  html += _dirSection('Contacto', 'fa-address-book',
    _dirField('dp-email',    'Correo de contacto', 'email', P.email,    'contacto@ejemplo.com')
   +_dirField('dp-phone',    'Teléfono',           'tel',   P.phone,    '+52 55 0000 0000')
   +_dirField('dp-whatsapp', 'WhatsApp',           'tel',   P.whatsapp, '+52 55 0000 0000', 'Con código de país')
   +_dirField('dp-website',  'Sitio web',          'url',   P.website,  'https://tuwebsite.com')
  );

  var hasCoords = !!(P.lat && P.lng);
  var btnStyle  = 'border:none;border-radius:12px;padding:.7rem .95rem;color:#fff;cursor:pointer;flex-shrink:0;font-size:.88rem;';
  html += _dirSection('Ubicación', 'fa-location-dot',
    // ── Barra de búsqueda + GPS ─────────────────────────────────────────────
    '<p style="font-size:.78rem;color:var(--tu);margin:0 0 .55rem;display:flex;align-items:center;gap:.35rem">'
    +'<i class="fas fa-map-location-dot" style="color:var(--vk-rose)"></i> Escribe tu dirección — los campos se completan solos</p>'
    +'<div style="display:flex;gap:.45rem;align-items:center">'
    // Input de dirección + dropdown de sugerencias
    +'<div style="position:relative;flex:1">'
    +'<i class="fas fa-map-pin" style="position:absolute;left:.85rem;top:50%;transform:translateY(-50%);color:var(--vk-rose);font-size:.85rem;pointer-events:none;z-index:1"></i>'
    +'<input id="dp-address" type="text" value="'+escHTML(P.address||'')+'" placeholder="Calle, número, ciudad..." autocomplete="off" '
    +'style="width:100%;background:rgba(255,255,255,.08);border:1.5px solid rgba(0,200,232,.25);border-radius:12px;'
    +'padding:.7rem .85rem .7rem 2.35rem;color:var(--td);font-size:.9rem;box-sizing:border-box;transition:border .2s" '
    +'onfocus="this.style.borderColor=\'rgba(0,200,232,.6)\'" onblur="this.style.borderColor=\'rgba(0,200,232,.25)\'">'
    // Dropdown de sugerencias Nominatim
    +'<div id="dp-autocomplete-list" style="display:none;position:absolute;top:calc(100% + 2px);left:0;right:0;'
    +'background:#1e2340;border:1px solid rgba(0,200,232,.25);border-radius:0 0 12px 12px;'
    +'z-index:9999;max-height:220px;overflow-y:auto;box-shadow:0 8px 24px rgba(0,0,0,.4)"></div>'
    +'</div>'
    // Botón: buscar dirección escrita
    +'<button type="button" id="dp-btn-search" onclick="_geocodeManual()" title="Buscar esta dirección" '
    +'style="background:rgba(0,136,204,.7);'+btnStyle+'">'
    +'<i class="fas fa-magnifying-glass"></i></button>'
    // Botón: GPS / ubicación actual
    +'<button type="button" id="dp-btn-gps" onclick="_useCurrentLocation()" title="Usar mi ubicación actual" '
    +'style="background:var(--grad-accent);'+btnStyle+'">'
    +'<i class="fas fa-location-crosshairs"></i></button>'
    +'</div>'
    // Ayuda rápida
    +'<p style="font-size:.72rem;color:var(--tu);margin:.35rem 0 0;opacity:.7">'
    +'<i class="fas fa-circle-info" style="margin-right:.25rem"></i>'
    +'Selecciona una sugerencia del dropdown, o escribe y pulsa <kbd style="background:rgba(255,255,255,.1);padding:.1rem .3rem;border-radius:3px">🔍</kbd>, '
    +'o usa <kbd style="background:rgba(255,255,255,.1);padding:.1rem .3rem;border-radius:3px">📍</kbd> para tu posición actual</p>'
    // Badge verificado
    +'<div id="dp-maps-badge" style="display:'+(hasCoords?'flex':'none')+';align-items:center;gap:.4rem;'
    +'font-size:.75rem;color:#4caf50;margin-top:.55rem;background:rgba(76,175,80,.1);'
    +'border:1px solid rgba(76,175,80,.2);border-radius:8px;padding:.3rem .65rem;width:fit-content">'
    +'<i class="fas fa-circle-check"></i> Ubicación verificada</div>'
    // Mini mapa — siempre visible pero vacío hasta que haya coords
    +'<div id="dp-map-preview" style="height:220px;border-radius:12px;overflow:hidden;'
    +'margin-top:.75rem;border:1px solid rgba(0,200,232,.18);position:relative;'
    +'display:'+(hasCoords?'block':'flex')+';align-items:center;justify-content:center;'
    +'background:rgba(0,136,204,.04)">'
    +(hasCoords
      ? ''  // _showDirMapPreview lo llenará
      : '<div style="text-align:center;color:var(--tu);padding:1rem">'
        +'<i class="fas fa-map-location-dot" style="font-size:2.5rem;color:rgba(0,200,232,.25);display:block;margin-bottom:.6rem"></i>'
        +'<p style="font-size:.8rem;margin:0;opacity:.7">El mapa aparece al buscar una dirección o usar tu ubicación</p>'
        +'</div>')
    +'</div>'
    // Tip mover marcador
    +'<p id="dp-map-tip" style="display:'+(hasCoords?'block':'none')+';font-size:.72rem;color:var(--tu);margin:.35rem 0 0;opacity:.7">'
    +'<i class="fas fa-hand-pointer" style="margin-right:.25rem"></i>Arrastra el marcador para ajustar la posición exacta</p>'
    // Campos detectados por Maps
    +'<div style="margin-top:.85rem">'
    +'<p style="font-size:.72rem;font-weight:700;color:var(--tu);margin:0 0 .5rem;text-transform:uppercase;letter-spacing:.05em">Detalles de ubicación</p>'
    +'<div style="display:grid;grid-template-columns:1fr .7fr .9fr;gap:.65rem">'
    +_dirField('dp-city',   'Ciudad',            'text', P.city,       'Ciudad de México')
    +_dirField('dp-postal', 'Código Postal',     'text', P.postal_code||'', '06600')
    +_dirField('dp-state',  'Estado / Provincia','text', P.state,      'CDMX')
    +'</div>'
    +_dirField('dp-country','País', 'text', P.country, 'México')
    +'</div>'
    // Coordenadas colapsables
    +'<details style="margin-top:.5rem"><summary style="font-size:.75rem;color:var(--tu);cursor:pointer;padding:.3rem 0;user-select:none">'
    +'<i class="fas fa-code" style="margin-right:.35rem"></i>Coordenadas GPS (avanzado)</summary>'
    +'<div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-top:.5rem">'
    +_dirField('dp-lat', 'Latitud',  'text', P.lat||'', '19.4326')
    +_dirField('dp-lng', 'Longitud', 'text', P.lng||'', '-99.1332')
    +'</div></details>'
  );
  // ── Información adicional ─────────────────────────────────────────────────
  var genderOpts = [['','Prefiero no decir'],['Hombre','Hombre'],['Mujer','Mujer'],['No binario','No binario']].map(function(o){
    return '<option value="'+o[0]+'"'+(P.gender===o[0]?' selected':'')+'>'+o[1]+'</option>';
  }).join('');
  var availOpts  = [['accepting','Aceptando nuevos pacientes'],['existing_only','Solo pacientes existentes'],['not_available','No disponible temporalmente']].map(function(o){
    return '<option value="'+o[0]+'"'+(( P.availability||'accepting')===o[0]?' selected':'')+'>'+o[1]+'</option>';
  }).join('');

  html += _dirSection('Información Adicional', 'fa-circle-info',
    '<div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">'
   +'<div style="margin-bottom:1rem"><label style="display:block;font-size:.78rem;font-weight:600;color:var(--tm);margin-bottom:.3rem">Género</label>'
   +'<select id="dp-gender" style="width:100%;background:var(--card);border:1px solid rgba(255,255,255,.08);color:var(--td);border-radius:10px;padding:.65rem .75rem;font-size:.87rem;outline:none">'+genderOpts+'</select></div>'
   +_dirField('dp-birth-year', 'Año de nacimiento', 'number', P.birth_year||'', '1985', 'Para calcular tu edad en el directorio')
   +'</div>'
   +'<div style="margin-bottom:1rem"><label style="display:block;font-size:.78rem;font-weight:600;color:var(--tm);margin-bottom:.3rem">Disponibilidad</label>'
   +'<select id="dp-availability" style="width:100%;background:var(--card);border:1px solid rgba(255,255,255,.08);color:var(--td);border-radius:10px;padding:.65rem .75rem;font-size:.87rem;outline:none">'+availOpts+'</select></div>'
   +_dirField('dp-languages', 'Idiomas', 'text', P.languages, 'Ej: Español, Inglés, Francés', 'Separa cada idioma con una coma (,). Ej: Español, Inglés')
  );

  // ── Horarios ──────────────────────────────────────────────────────────────
  var schedDays = [['lunes','Lunes'],['martes','Martes'],['miercoles','Miércoles'],['jueves','Jueves'],['viernes','Viernes'],['sabado','Sábado'],['domingo','Domingo']];
  var sched     = {};
  try { sched = JSON.parse(P.schedule_json || '{}'); } catch(e){ sched = {}; }
  var schedHtml = '<div style="overflow-x:auto"><table style="width:100%;border-collapse:collapse;font-size:.83rem">'
    +'<thead><tr>'
    +'<th style="text-align:left;padding:.4rem .5rem;color:var(--tu);font-weight:600">Día</th>'
    +'<th style="padding:.4rem .5rem;color:var(--tu);font-weight:600">Abre</th>'
    +'<th style="padding:.4rem .5rem;color:var(--tu);font-weight:600">Cierra</th>'
    +'<th style="padding:.4rem .5rem;color:var(--tu);font-weight:600">Cerrado</th>'
    +'</tr></thead><tbody>';
  schedDays.forEach(function(d){
    var key    = d[0];
    var label  = d[1];
    var ds     = sched[key] || {};
    var closed = ds.closed ? 'checked' : '';
    var open   = ds.open   || '08:00';
    var close  = ds.close  || '18:00';
    schedHtml += '<tr>'
      +'<td style="padding:.4rem .5rem;color:var(--td);font-weight:600">'+label+'</td>'
      +'<td style="padding:.4rem .3rem"><input type="time" id="sched-'+key+'-open" value="'+open+'" onchange="_schedClosed(\''+key+'\')" '
      +'style="background:var(--bg);border:1px solid rgba(255,255,255,.08);color:var(--td);border-radius:8px;padding:.35rem .5rem;font-size:.82rem;width:100%"></td>'
      +'<td style="padding:.4rem .3rem"><input type="time" id="sched-'+key+'-close" value="'+close+'" onchange="_schedClosed(\''+key+'\')" '
      +'style="background:var(--bg);border:1px solid rgba(255,255,255,.08);color:var(--td);border-radius:8px;padding:.35rem .5rem;font-size:.82rem;width:100%"></td>'
      +'<td style="padding:.4rem .5rem;text-align:center"><input type="checkbox" id="sched-'+key+'-closed" '+closed+' onchange="_schedToggleClosed(\''+key+'\')" '
      +'style="width:18px;height:18px;cursor:pointer"></td>'
      +'</tr>';
  });
  schedHtml += '</tbody></table></div>';
  html += _dirSection('Horarios de Atención', 'fa-clock', schedHtml);

  html += _dirSection('Redes Sociales', 'fa-share-nodes',
    _dirField('dp-facebook',  'Facebook',    'url', P.facebook,  'https://facebook.com/tupagina')
   +_dirField('dp-instagram', 'Instagram',   'url', P.instagram, 'https://instagram.com/tuperfil')
   +_dirField('dp-tiktok',    'TikTok',      'url', P.tiktok,    'https://tiktok.com/@tuperfil')
   +_dirField('dp-linkedin',  'LinkedIn',    'url', P.linkedin,  'https://linkedin.com/in/tuperfil')
   +_dirField('dp-youtube',   'YouTube',     'url', P.youtube,   'https://youtube.com/@tucanal')
   +_dirField('dp-twitter',   'X (Twitter)', 'url', P.twitter,   'https://x.com/tuperfil')
  );

  html += '</div>'; // end dir-sections-grid

  // Botones acción
  var isEdit = !!(P.post_id);
  html += '<button onclick="saveDirListing()" id="dir-save-btn" '
    +'style="width:100%;background:var(--grad-accent);color:#fff;border:none;border-radius:50px;'
    +'padding:.9rem;font-size:.95rem;font-weight:800;cursor:pointer;margin-top:.5rem;'
    +'box-shadow:0 4px 20px rgba(0,200,232,.3)">'
    +'<i class="fas fa-floppy-disk"></i> '+(isEdit?'Actualizar perfil':'Crear mi perfil en el directorio')+'</button>';

  // Botones secundarios: Restablecer y (si existe perfil) Eliminar
  html += '<div style="display:flex;gap:.6rem;margin-top:.6rem">';
  html += '<button onclick="resetDirForm()" '
    +'style="flex:1;background:transparent;color:var(--tm);border:1.5px solid rgba(255,255,255,.15);'
    +'border-radius:50px;padding:.75rem .9rem;font-size:.85rem;font-weight:700;cursor:pointer;'
    +'transition:border-color .2s,color .2s" '
    +'onmouseover="this.style.borderColor=\'rgba(0,200,232,.4)\';this.style.color=\'var(--vk-rose)\'" '
    +'onmouseout="this.style.borderColor=\'rgba(255,255,255,.15)\';this.style.color=\'var(--tm)\'">'
    +'<i class="fas fa-rotate-left"></i> Restablecer formulario</button>';
  if(isEdit){
    html += '<button onclick="deleteDirListing()" '
      +'style="flex:1;background:transparent;color:#e05252;border:1.5px solid rgba(224,82,82,.3);'
      +'border-radius:50px;padding:.75rem .9rem;font-size:.85rem;font-weight:700;cursor:pointer;'
      +'transition:border-color .2s,background .2s" '
      +'onmouseover="this.style.background=\'rgba(224,82,82,.1)\';this.style.borderColor=\'rgba(224,82,82,.6)\'" '
      +'onmouseout="this.style.background=\'transparent\';this.style.borderColor=\'rgba(224,82,82,.3)\'">'
      +'<i class="fas fa-trash-can"></i> Eliminar perfil</button>';
  }
  html += '</div>';

  if(isEdit && P.permalink && isApproved){
    html += '<a href="'+escHTML(P.permalink)+'" target="_blank" '
      +'style="display:flex;align-items:center;justify-content:center;gap:.6rem;margin-top:.65rem;'
      +'padding:.75rem;border-radius:50px;border:1.5px solid rgba(0,200,232,.35);'
      +'background:rgba(0,200,232,.05);color:var(--vk-rose);text-decoration:none;'
      +'font-size:.88rem;font-weight:700">'
      +'<i class="fas fa-eye"></i> Ver mi perfil en el directorio</a>';
  }

  // Banner de estado si hay perfil con aprobación pendiente o rechazado
  if(_dirProfile && _dirProfile.approval_status === 'pending'){
    html += '<div style="margin-bottom:1rem;padding:1rem 1.1rem;background:rgba(255,193,7,.1);'
      +'border:1px solid rgba(255,193,7,.4);border-radius:12px">'
      +'<p style="margin:0 0 .3rem;font-weight:700">🕐 Pendiente de aprobación</p>'
      +'<p style="margin:0;font-size:.85rem;color:var(--tm)">Tu perfil fue recibido y está siendo revisado por un administrador. '
      +'Una vez aprobado aparecerá en el directorio.</p></div>';
  } else if(_dirProfile && _dirProfile.approval_status === 'rejected'){
    html += '<div style="margin-bottom:1rem;padding:1rem 1.1rem;background:rgba(224,82,82,.08);'
      +'border:1px solid rgba(224,82,82,.3);border-radius:12px">'
      +'<p style="margin:0 0 .3rem;font-weight:700">⛔ Perfil no aprobado</p>'
      +'<p style="margin:0;font-size:.85rem;color:var(--tm)">Tu perfil fue revisado y no pudo ser aprobado en este momento. '
      +'Puedes editarlo y volver a enviarlo para revisión.</p></div>';
  }
  html += '<div id="dir-success-bar" style="display:none;margin-top:.75rem;padding:.75rem 1rem;'
    +'border:1px solid transparent;border-radius:12px;font-size:.85rem"></div>';

  wrap.innerHTML = html;
  // Activar pills de categorías — usar 'change' para leer estado YA actualizado
  wrap.querySelectorAll('.dp-cat-pill input[type="checkbox"]').forEach(function(cb){
    cb.addEventListener('change',function(){
      var lbl=cb.closest('.dp-cat-pill');
      if(!lbl)return;
      lbl.style.background  = cb.checked?'rgba(0,200,232,.15)':'rgba(255,255,255,.05)';
      lbl.style.borderColor = cb.checked?'rgba(0,200,232,.5)':'rgba(255,255,255,.1)';
    });
  });
  setTimeout(_initDirMaps, 150);
}

/* ── Ubicación — Leaflet + Nominatim (sin billing, 100% gratuito) ────────── */
var _leafMap    = null;
var _leafMarker = null;
var _nomTimer   = null;

/* Helpers de input */
function _gv(id){ return (document.getElementById(id)||{value:''}).value.trim(); }
function _setInputVal(id, val){ var e=document.getElementById(id); if(e) e.value=val; }

/* Carga dinámica de Leaflet si el CDN no cargó al inicio */
function _ensureLeaflet(cb){
  if(window.L){ cb(false); return; }
  var l=document.createElement('link');
  l.rel='stylesheet';
  l.href='https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.css';
  document.head.appendChild(l);
  var s=document.createElement('script');
  s.src='https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.js';
  s.onload=function(){ cb(false); };
  s.onerror=function(){ cb(true); };
  document.head.appendChild(s);
}

function _initDirMaps(){
  _bindNominatimAC();
  var lat0=parseFloat(_gv('dp-lat')), lng0=parseFloat(_gv('dp-lng'));
  if(lat0&&lng0){
    _ensureLeaflet(function(err){
      if(err){ _showMapIframe(document.getElementById('dp-map-preview'),lat0,lng0); }
      else   { _drawLeaflet(document.getElementById('dp-map-preview'),lat0,lng0,''); }
    });
  }
}

function _bindNominatimAC(){
  var input=document.getElementById('dp-address');
  var list=document.getElementById('dp-autocomplete-list');
  if(!input||!list||input._acBound) return;
  input._acBound=true;
  input.addEventListener('keydown',function(e){if(e.key==='Enter')e.preventDefault();});
  input.addEventListener('input',function(){
    clearTimeout(_nomTimer);
    var q=input.value.trim();
    if(q.length<3){list.style.display='none';return;}
    _nomTimer=setTimeout(function(){_nominatimSearch(q,list);},500);
  });
  document.addEventListener('click',function(e){if(e.target!==input)list.style.display='none';});
}

async function _nominatimSearch(q,list){
  try{
    var url='https://nominatim.openstreetmap.org/search?q='+encodeURIComponent(q)
      +'&format=json&addressdetails=1&limit=6&accept-language=es';
    var r=await fetch(url);
    var results=await r.json();
    _renderNomList(results,list);
  }catch(e){if(list)list.style.display='none';}
}

function _renderNomList(results,list){
  if(!list)return;
  if(!results||!results.length){list.style.display='none';return;}
  list.innerHTML='';
  results.forEach(function(item){
    var div=document.createElement('div');
    div.style.cssText='padding:.55rem .85rem;font-size:.82rem;color:var(--td);cursor:pointer;border-bottom:1px solid rgba(255,255,255,.05);line-height:1.4';
    var parts=(item.display_name||'').split(',');
    var main=parts[0].trim();
    var rest=parts.slice(1,3).join(',').trim();
    div.innerHTML='<strong style="color:var(--td)">'+escHTML(main)+'</strong>'
      +(rest?'<br><span style="font-size:.75rem;color:var(--tu)">'+escHTML(rest)+'</span>':'');
    div.addEventListener('mousedown',function(e){e.preventDefault();});
    div.addEventListener('click',function(){_applyNominatimResult(item);list.style.display='none';});
    div.addEventListener('mouseover',function(){div.style.background='rgba(0,200,232,.1)';});
    div.addEventListener('mouseout',function(){div.style.background='';});
    list.appendChild(div);
  });
  list.style.display='block';
}

function _applyNominatimResult(item){
  var a=item.address||{};
  var city=a.city||a.town||a.village||a.municipality||a.county||'';
  var state=a.state||a.region||a.province||'';
  _fillLocationFields(item.display_name||'',city,state,a.country||'',parseFloat(item.lat),parseFloat(item.lon),a.postcode||'');
}

function _fillLocationFields(full,city,state,country,lat,lng,postal){
  _setInputVal('dp-address',full);
  _setInputVal('dp-city',city);
  _setInputVal('dp-postal',postal||'');
  _setInputVal('dp-state',state);
  _setInputVal('dp-country',country);
  _setInputVal('dp-lat',lat.toFixed(6));
  _setInputVal('dp-lng',lng.toFixed(6));
  var badge=document.getElementById('dp-maps-badge');
  if(badge)badge.style.display='flex';
  var tip=document.getElementById('dp-map-tip');
  if(tip)tip.style.display='block';
  _showDirMapPreview(lat,lng,full);
}

async function _geocodeManual(){
  var addr=_gv('dp-address');
  if(!addr){showToast('Escribe una dirección primero.');return;}
  var btn=document.getElementById('dp-btn-search');
  if(btn){btn.innerHTML='<i class="fas fa-spinner fa-spin"></i>';btn.disabled=true;}
  try{
    var url='https://nominatim.openstreetmap.org/search?q='+encodeURIComponent(addr)
      +'&format=json&addressdetails=1&limit=1&accept-language=es';
    var r=await fetch(url);
    var results=await r.json();
    if(results&&results[0]){
      _applyNominatimResult(results[0]);
    }else{
      showToast('No se encontró esa dirección.');
    }
  }catch(e){showToast('Error al buscar.');}
  finally{if(btn){btn.innerHTML='<i class="fas fa-magnifying-glass"></i>';btn.disabled=false;}}
}

async function _reverseGeocode(lat,lng){
  try{
    var url='https://nominatim.openstreetmap.org/reverse?lat='+lat+'&lon='+lng
      +'&format=json&addressdetails=1&accept-language=es';
    var r=await fetch(url);
    var data=await r.json();
    if(data&&data.address){
      var a=data.address;
      _setInputVal('dp-address',data.display_name||'');
      _setInputVal('dp-city',a.city||a.town||a.village||a.municipality||a.county||'');
      _setInputVal('dp-state',a.state||a.region||a.province||'');
      _setInputVal('dp-country',a.country||'');
      var badge=document.getElementById('dp-maps-badge');
      if(badge)badge.style.display='flex';
    }
  }catch(e){}
}

function _useCurrentLocation(){
  if(!navigator.geolocation){showToast('Tu navegador no soporta geolocalización.');return;}
  var btn=document.getElementById('dp-btn-gps');
  if(btn){btn.innerHTML='<i class="fas fa-spinner fa-spin"></i>';btn.disabled=true;}
  navigator.geolocation.getCurrentPosition(
    function(pos){
      if(btn){btn.innerHTML='<i class="fas fa-location-crosshairs"></i>';btn.disabled=false;}
      var lat=pos.coords.latitude, lng=pos.coords.longitude;
      _setInputVal('dp-lat',lat.toFixed(6));
      _setInputVal('dp-lng',lng.toFixed(6));
      _showDirMapPreview(lat,lng,'Mi ubicación actual');
      _reverseGeocode(lat,lng);
    },
    function(err){
      if(btn){btn.innerHTML='<i class="fas fa-location-crosshairs"></i>';btn.disabled=false;}
      var msg={1:'Permiso denegado. Actiúlo en Configuración.',
               2:'No se pudo obtener tu ubicación.',3:'Tiempo agotado.'};
      showToast(msg[err.code]||'Error al obtener ubicación.');
    },
    {enableHighAccuracy:true,timeout:10000,maximumAge:0}
  );
}

/* Mapa de ubicación en el formulario ─────────────────────────────────────
   Estrategia:
   1. Si window.L está listo → _drawLeaflet directamente
   2. Si no → intentar carga dinámica del CDN
   3. Si el CDN falla → iframe OSM embed (siempre funciona)
*/
function _showDirMapPreview(lat,lng,title){
  var container=document.getElementById('dp-map-preview');
  if(!container) return;
  container.style.display='block';
  container.style.alignItems='';
  container.style.justifyContent='';
  var badge=document.getElementById('dp-maps-badge');
  if(badge) badge.style.display='flex';
  var tip=document.getElementById('dp-map-tip');
  if(tip) tip.style.display='block';

  if(window.L){
    _drawLeaflet(container,lat,lng,title);
    return;
  }
  /* Leaflet aún no disponible: mostrar spinner y cargar dinámicamente */
  container.innerHTML='<div style="display:flex;align-items:center;justify-content:center;height:100%;flex-direction:column;gap:.5rem">'
    +'<i class="fas fa-spinner fa-spin" style="font-size:1.6rem;color:rgba(0,200,232,.6)"></i>'
    +'<p style="font-size:.78rem;color:var(--tu);margin:0;opacity:.8">Cargando mapa...</p></div>';
  _ensureLeaflet(function(err){
    var cont=document.getElementById('dp-map-preview');
    if(!cont) return;
    if(err||!window.L){ _showMapIframe(cont,lat,lng); }
    else               { _drawLeaflet(cont,lat,lng,title); }
  });
}

function _showMapIframe(container,lat,lng){
  /* Fallback cuando Leaflet no carga: embed iframe de OpenStreetMap */
  if(!container) return;
  var d=0.008;
  container.innerHTML='<iframe src="https://www.openstreetmap.org/export/embed.html?bbox='
    +(lng-d)+'%2C'+(lat-d)+'%2C'+(lng+d)+'%2C'+(lat+d)
    +'&layer=mapnik&marker='+lat+'%2C'+lng
    +'" style="width:100%;height:100%;border:0" loading="lazy"></iframe>';
}

function _drawLeaflet(container,lat,lng,title){
  if(!container||!window.L) return;
  if(_leafMap){ try{ _leafMap.remove(); }catch(e){} _leafMap=null; _leafMarker=null; }
  container.innerHTML='';
  _leafMap=L.map(container,{zoomControl:true,scrollWheelZoom:false}).setView([lat,lng],15);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{
    attribution:'&copy; <a href="https://openstreetmap.org/copyright">OpenStreetMap</a>',
    maxZoom:19,
  }).addTo(_leafMap);
  var icon=L.divIcon({
    html:'<div style="width:20px;height:20px;background:#00c8e8;border:3px solid #fff;border-radius:50%;box-shadow:0 2px 12px rgba(0,200,232,.7)"></div>',
    className:'',iconSize:[20,20],iconAnchor:[10,10],
  });
  _leafMarker=L.marker([lat,lng],{draggable:true,icon:icon}).addTo(_leafMap);
  /* invalidateSize después del primer paint — soluciona tiles grises */
  setTimeout(function(){ if(_leafMap) _leafMap.invalidateSize(); },250);
  _leafMarker.on('dragend',function(e){
    var p=e.target.getLatLng();
    _setInputVal('dp-lat',p.lat.toFixed(6));
    _setInputVal('dp-lng',p.lng.toFixed(6));
    var badge=document.getElementById('dp-maps-badge');
    if(badge) badge.style.display='flex';
    _reverseGeocode(p.lat,p.lng);
  });
}

/* Subida de imágenes ─────────────────────────────────────────────────────── */

async function uploadDirImage(input, fieldId){
  var file = input.files[0];
  if(!file) return;
  var lblEl = document.getElementById(fieldId+'-lbl');

  // Validar formato
  var allowed = ['image/jpeg','image/jpg','image/png','image/tiff'];
  if(allowed.indexOf(file.type) === -1){
    showToast('❌ Formato no permitido. Usa JPG, JPEG, PNG o TIFF.');
    input.value = '';
    return;
  }
  // Validar peso máximo: 5 MB
  var maxMB = 5;
  if(file.size > maxMB * 1024 * 1024){
    showToast('❌ La imagen supera los '+maxMB+' MB. Reduce el tamaño antes de subir.');
    input.value = '';
    return;
  }

  if(lblEl) lblEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Subiendo...';
  // Determinar tipo: logo o featured según el campo
  var imgType = (fieldId === 'dp-img-logo') ? 'logo' : 'featured';
  try{
    var fd = new FormData();
    fd.append('file', file);
    fd.append('type', imgType);
    var r = await fetch(apiURL('/vk/v1/dir/upload-image'), {
      method: 'POST',
      headers: {'X-VK-Token': ST.token||''},
      body: fd,
    });
    var res = await r.json();
    if(!r.ok || !res.ok) throw new Error(res.message||'Error al subir imagen (HTTP '+r.status+')');

    // Actualizar preview
    var previewEl = document.getElementById(fieldId+'-preview');
    if(previewEl){
      var img = document.createElement('img');
      img.src = res.url;
      img.id  = fieldId+'-preview';
      img.style.cssText = 'width:56px;height:56px;border-radius:10px;object-fit:cover;flex-shrink:0';
      previewEl.parentNode.replaceChild(img, previewEl);
    }
    // Guardar ID en el campo hidden
    var hidEl = document.getElementById(fieldId+'-id');
    if(hidEl) hidEl.value = res.id;
    // Actualizar perfil en memoria
    if(_dirProfile){
      if(imgType === 'logo'){ _dirProfile.logo_id = res.id; _dirProfile.logo = res.url; }
      else { _dirProfile.featured_image_id = res.id; _dirProfile.featured_image = res.url; }
    }
    if(lblEl) lblEl.innerHTML = '<i class="fas fa-check" style="color:var(--vk-rose)"></i> Imagen cargada';
  }catch(e){
    console.error('[Dir] uploadDirImage error:', e);
    showToast('❌ '+e.message);
    if(lblEl) lblEl.innerHTML = '<i class="fas fa-arrow-up-from-bracket"></i> Subir imagen';
  }
}

/* ── Helpers de horarios ──────────────────────────────────────────────────── */
function _schedToggleClosed(day){
  var cb = document.getElementById('sched-'+day+'-closed');
  var oi = document.getElementById('sched-'+day+'-open');
  var ci = document.getElementById('sched-'+day+'-close');
  if(!cb) return;
  if(oi) oi.disabled = cb.checked;
  if(ci) ci.disabled = cb.checked;
}
function _schedClosed(day){ /* no-op: fires on time change */ }
function _buildSchedJson(){
  var days   = ['lunes','martes','miercoles','jueves','viernes','sabado','domingo'];
  var result = {};
  days.forEach(function(d){
    var cb = document.getElementById('sched-'+d+'-closed');
    var oi = document.getElementById('sched-'+d+'-open');
    var ci = document.getElementById('sched-'+d+'-close');
    result[d] = {
      closed: cb ? cb.checked : false,
      open:   (oi && oi.value) ? oi.value : '08:00',
      close:  (ci && ci.value) ? ci.value : '18:00',
    };
  });
  return JSON.stringify(result);
}

/* Guardar perfil ─────────────────────────────────────────────────────────── */

async function saveDirListing(){
  if(_dirSaving) return;
  _dirSaving = true;
  var btn = document.getElementById('dir-save-btn');
  if(btn){ btn.disabled=true; btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Guardando...'; }

  function _fv(id){ return (document.getElementById(id)||{value:''}).value.trim(); }

  var catBoxes = document.querySelectorAll('#dp-categories-wrap input[type="checkbox"]:checked');
  var catIds   = Array.from(catBoxes).map(function(cb){ return parseInt(cb.value,10); });

  var payload = {
    name:             _fv('dp-name'),
    tagline:          _fv('dp-tagline'),
    bio:              _fv('dp-bio'),
    email:            _fv('dp-email'),
    phone:            _fv('dp-phone'),
    whatsapp:         _fv('dp-whatsapp'),
    website:          _fv('dp-website'),
    address:          _fv('dp-address'),
    city:             _fv('dp-city'),
    postal_code:      _fv('dp-postal'),
    state:            _fv('dp-state'),
    country:          _fv('dp-country'),
    lat:              _fv('dp-lat'),
    lng:              _fv('dp-lng'),
    profession:       _fv('dp-profession'),
    specialty:        _fv('dp-specialty'),
    experience:       _fv('dp-experience'),
    price_range:      _fv('dp-price-range'),
    services:         _fv('dp-services'),
    facebook:         _fv('dp-facebook'),
    instagram:        _fv('dp-instagram'),
    twitter:          _fv('dp-twitter'),
    linkedin:         _fv('dp-linkedin'),
    youtube:          _fv('dp-youtube'),
    tiktok:           _fv('dp-tiktok'),
    featured_image_id: parseInt(_fv('dp-img-featured-id'),10)||0,
    logo_id:           parseInt(_fv('dp-img-logo-id'),10)||0,
    category_ids:      catIds,
    gender:       _fv('dp-gender'),
    birth_year:   parseInt(_fv('dp-birth-year'),10)||0,
    availability: (document.getElementById('dp-availability')||{value:'accepting'}).value,
    languages:    _fv('dp-languages'),
    technologies: (function(){
      var cbs=document.querySelectorAll('#dp-tech-wrap input[type="checkbox"]:checked');
      return Array.from(cbs).map(function(cb){return cb.value;}).join(', ');
    })(),
    schedule_json: _buildSchedJson(),
  };

  try{
    var res = await postJSON(C.API_BASE + '/vk/v1/dir/profile', payload, 'POST', {'X-VK-Token': ST.token||''});
    if(!res || !res.ok) throw new Error((res&&res.message)||'Error al guardar el perfil');

    // Actualizar el perfil en memoria con la respuesta del servidor
    _dirProfile = res.profile || _dirProfile;
    _dirSetSubtitle(res.approval_status === 'approved');

    var isPending = res.save_result === 'pending';
    var bar = document.getElementById('dir-success-bar');
    if(bar){
      bar.style.display = 'block';
      if(isPending){
        bar.style.background  = 'rgba(255,193,7,.1)';
        bar.style.borderColor = 'rgba(255,193,7,.4)';
        bar.style.borderRadius = '12px';
        bar.style.padding = '1rem 1.1rem';
        bar.innerHTML = '<p style="margin:0 0 .4rem;font-weight:700;font-size:1rem">🕐 ¡Tu perfil ha sido enviado correctamente!</p>'
          + '<p style="margin:0;font-size:.88rem;line-height:1.6;color:var(--tm)">Tu anuncio se encuentra <strong>pendiente de aprobación</strong>. '
          + 'Un administrador revisará la información y, una vez aprobada, será publicada en el directorio. '
          + 'Te notificaremos cuando el proceso haya finalizado.</p>';
      } else {
        bar.style.background  = 'rgba(0,200,100,.08)';
        bar.style.borderColor = 'rgba(0,200,100,.25)';
        bar.style.borderRadius = '12px';
        bar.style.padding = '1rem 1.1rem';
        bar.innerHTML = '✅ ' + escHTML(res.message)
          + (res.permalink ? ' &nbsp;·&nbsp; <a href="'+escHTML(res.permalink)+'" target="_blank" '
              +'style="color:var(--vk-rose);font-weight:700;text-decoration:none">'
              +'<i class="fas fa-globe"></i> Ver en directorio →</a>' : '');
      }
    }
    showToast(isPending ? '🕐 Perfil enviado — pendiente de aprobación' : '✅ '+res.message);

    // Re-renderizar para mostrar la barra de estado y datos actualizados
    setTimeout(function(){
      _renderDirForm();
    }, 800);

  }catch(e){
    console.error('[Dir] saveDirListing error:', e);
    showToast('❌ '+e.message);
    var bar = document.getElementById('dir-success-bar');
    if(bar){
      bar.style.display     = 'block';
      bar.style.background  = 'rgba(224,82,82,.1)';
      bar.style.borderColor = 'rgba(224,82,82,.3)';
      bar.innerHTML = '❌ ' + escHTML(e.message);
    }
  }finally{
    _dirSaving = false;
    if(btn){
      btn.disabled = false;
      var isEdit = _dirProfile && _dirProfile.post_id;
      btn.innerHTML = '<i class="fas fa-floppy-disk"></i> '
        + (isEdit ? 'Actualizar perfil' : 'Crear mi perfil en el directorio');
    }
  }
}

/* Restablecer formulario ─────────────────────────────────────────────────── */

function resetDirForm(){
  // Borra _dirProfile para que el formulario se cargue vacío y vuelve a renderizar
  _dirProfile = null;
  _renderDirForm();
  showToast('Formulario restablecido.');
}

/* Eliminar perfil profesional ───────────────────────────────────────────── */

var _dirDeleting = false;

function deleteDirListing(){
  if(_dirDeleting) return;

  // Modal de confirmación nativo con dos pasos para evitar borrado accidental
  var first = confirm('¿Estás seguro de que deseas eliminar tu perfil profesional?\n\nEsta acción eliminará tu anuncio del directorio de forma permanente.');
  if(!first) return;
  var second = confirm('⚠️ Confirmación final\n\nSe eliminarán todos tus datos del directorio (fotos, información de contacto, horarios, etc.).\n\nEsta acción NO se puede deshacer.\n\n¿Continuar?');
  if(!second) return;

  _dirDeleting = true;

  fetch(apiURL('/vk/v1/dir/profile'), {
    method: 'DELETE',
    headers: { 'Content-Type': 'application/json', 'X-VK-Token': ST.token || '' },
  })
  .then(function(r){ return r.json(); })
  .then(function(res){
    if(!res || !res.ok) throw new Error((res && res.message) || 'Error al eliminar el perfil');
    _dirProfile = null;
    showToast('✅ ' + res.message);
    // Re-renderizar el formulario vacío
    setTimeout(function(){ _renderDirForm(); }, 600);
  })
  .catch(function(e){
    console.error('[Dir] deleteDirListing error:', e);
    showToast('❌ ' + e.message);
  })
  .finally(function(){
    _dirDeleting = false;
  });
}




/* ═══════════════════════════════════════════════════════════
   vkQA — Preguntas y Respuestas
   ═══════════════════════════════════════════════════════════ */
var vkQA = (function(){
  var _qaData     = [];
  var _qaFiltered = [];
  var _qaFilter   = 'all';
  var _qaSort     = 'recent';
  var _qaSelectedTopic = '';
  var _qaStylesInjected = false;

  /* Paleta: verde oscuro #1b4332 | naranja #e25c2e | fondo #f5f0ea */
  function _injectStyles(){
    if(_qaStylesInjected) return;
    _qaStylesInjected = true;
    var css =
      '#screen-qa{background:#f5f0ea}' +
      '#screen-qa .scroll-area{background:#f5f0ea;padding-bottom:2rem}' +
      '.qa-hero-card{background:#fff;border-radius:0 0 20px 20px;padding:1.25rem 1rem 1rem;margin-bottom:.5rem;box-shadow:0 2px 8px rgba(0,0,0,.06)}' +
      '.qa-hero-title{font-size:1.05rem;font-weight:800;color:#1a1a1a;margin:0 0 .9rem;line-height:1.35}' +
      '.qa-search-row{padding:0 0 .75rem}' +
      '.qa-search-wrap{position:relative}' +
      '.qa-search-icon{position:absolute;left:.9rem;top:50%;transform:translateY(-50%);color:#9a8e84;font-size:.85rem;pointer-events:none}' +
      '.qa-search-input{width:100%;box-sizing:border-box;padding:.7rem 1rem .7rem 2.5rem;border:1.5px solid #ece6de;border-radius:12px;font-size:.9rem;background:#f9f6f2;color:#1a1a1a;outline:none;font-family:inherit;transition:border-color .18s}' +
      '.qa-search-input:focus{border-color:#1b4332;background:#fff}' +
      '.qa-search-input::placeholder{color:#b8b0a8}' +
      '.qa-cta-btn-wrap{padding:0}' +
      '.qa-cta-btn{display:flex;align-items:center;justify-content:center;gap:.5rem;width:100%;padding:.85rem 1rem;background:#e25c2e;color:#fff;border:none;border-radius:14px;font-size:.95rem;font-weight:800;cursor:pointer;box-shadow:0 3px 10px rgba(226,92,46,.35);transition:opacity .15s}' +
      '.qa-cta-btn:hover{opacity:.92}' +
      '.qa-feed-controls{background:#f5f0ea}' +
      '.filters-label{padding:.75rem 1rem .25rem;font-size:.7rem;font-weight:800;text-transform:uppercase;letter-spacing:.8px;color:#8a7e74}' +
      '.filters-row{display:flex;gap:.45rem;overflow-x:auto;padding:.4rem 1rem .6rem;scrollbar-width:none}' +
      '.filters-row::-webkit-scrollbar{display:none}' +
      '.chip{display:inline-flex;align-items:center;gap:.35rem;white-space:nowrap;border:1.5px solid #ddd6ce;background:#fff;color:#7a6e64;border-radius:20px;padding:.35rem .9rem;font-size:.78rem;font-weight:600;cursor:pointer;transition:all .18s}' +
      '.chip svg{width:11px;height:11px;opacity:.7}' +
      '.chip.active{background:#1b4332;color:#fff;border-color:#1b4332}' +
      '.chip.active svg{opacity:1}' +
      '.sort-row{display:flex;justify-content:space-between;align-items:center;padding:.2rem 1rem .65rem}' +
      '.sort-count{font-size:.8rem;color:#8a7e74;font-weight:600}' +
      '.sort-count strong{color:#3d2f24}' +
      '.sort-select{display:inline-flex;align-items:center;gap:.3rem;font-size:.8rem;font-weight:700;background:#fff;border:1.5px solid #ddd6ce;border-radius:10px;padding:.32rem .8rem;color:#3d2f24;cursor:pointer}' +
      '.sort-select svg{width:13px;height:13px;color:#8a7e74}' +
      '.qa-list-wrap{padding:0 1rem 1.5rem}' +
      '.qa-card{background:#fff;border-radius:16px;padding:1rem;margin-bottom:.7rem;box-shadow:0 1px 5px rgba(0,0,0,.07);cursor:pointer;transition:transform .15s,box-shadow .15s;display:flex;flex-direction:column}' +
      '.qa-card:hover{box-shadow:0 4px 14px rgba(0,0,0,.11);transform:translateY(-1px)}' +
      '.qa-card:active{transform:scale(.985)}' +
      '.qa-card-badges-row{display:flex;gap:.4rem;margin-bottom:.5rem;flex-wrap:wrap}' +
      '.qa-badge{display:inline-flex;align-items:center;gap:.3rem;font-size:.71rem;font-weight:700;border-radius:20px;padding:.2rem .65rem}' +
      '.qa-badge-dot{width:7px;height:7px;border-radius:50%;flex-shrink:0;display:inline-block}' +
      '.qa-badge-resolved{background:#ecfdf5;color:#166534}.qa-badge-resolved .qa-badge-dot{background:#16a34a}' +
      '.qa-badge-waiting{background:#fff7ed;color:#9a3412}.qa-badge-waiting .qa-badge-dot{background:#ea580c}' +
      '.qa-badge-none{background:#fff1f2;color:#9f1239}.qa-badge-none .qa-badge-dot{background:#e11d48}' +
      '.qa-badge-teacher{background:#fefce8;color:#854d0e;border:1px solid #fde047}' +
      '.qa-card-title{font-size:.94rem;font-weight:800;color:#1a1a1a;line-height:1.38;margin-bottom:.3rem}' +
      '.qa-card-excerpt{font-size:.81rem;color:#6b5e53;line-height:1.5;margin-bottom:.65rem;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}' +
      '.qa-card-footer{display:flex;align-items:center;gap:.5rem;margin-top:auto;flex-wrap:wrap}' +
      '.qa-avatar{border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-weight:800;color:#fff;flex-shrink:0}' +
      '.qa-author-name{font-size:.77rem;font-weight:600;color:#4a3f36}' +
      '.qa-footer-sep{color:#ccc5be;font-size:.7rem;flex-shrink:0}' +
      '.qa-footer-meta{display:flex;align-items:center;gap:.25rem;font-size:.75rem;color:#9a8e84;font-weight:600}' +
      '.qa-footer-meta svg{width:11px;height:11px;opacity:.65}' +
      '#qa-view-new{padding:1rem 1rem 2.5rem}' +
      '.qa-nq-section{margin-bottom:1.25rem}' +
      '.qa-nq-label{display:block;font-size:.9rem;font-weight:800;color:#2d2418;margin-bottom:.2rem}' +
      '.qa-nq-sublabel{display:block;font-size:.78rem;color:#8a7e74;margin-bottom:.55rem}' +
      '.qa-nq-input{width:100%;box-sizing:border-box;padding:.75rem 1rem;border:1.5px solid #e8e1da;border-radius:12px;font-size:.9rem;background:#fff;color:#1a1a1a;outline:none;font-family:inherit}' +
      '.qa-nq-input:focus{border-color:#1b4332}' +
      '.qa-nq-textarea{width:100%;box-sizing:border-box;padding:.75rem 1rem;border:1.5px solid #e8e1da;border-radius:12px;font-size:.88rem;background:#fff;color:#1a1a1a;outline:none;resize:vertical;font-family:inherit}' +
      '.qa-nq-textarea:focus{border-color:#1b4332}' +
      '.qa-char-count{font-size:.74rem;color:#b0a89e;text-align:right;margin:.2rem 0 0}' +
      '.qa-topic-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:.6rem}' +
      '.qa-topic-tile{display:flex;flex-direction:column;align-items:center;gap:.5rem;padding:.9rem .5rem;border-radius:14px;background:#fff;border:2px solid #e8e1da;cursor:pointer;transition:all .2s}' +
      '.qa-topic-tile:hover{border-color:#1b4332;background:#f0fdf4}' +
      '.qa-topic-tile.selected{border-color:#1b4332;background:#1b4332}' +
      '.qa-topic-tile.selected .qa-topic-name{color:#fff}' +
      '.qa-topic-icon{width:40px;height:40px;border-radius:12px;background:rgba(27,67,50,.1);display:flex;align-items:center;justify-content:center}' +
      '.qa-topic-tile.selected .qa-topic-icon{background:rgba(255,255,255,.2)}' +
      '.qa-topic-icon svg{color:#1b4332}' +
      '.qa-topic-tile.selected .qa-topic-icon svg{color:#fff}' +
      '.qa-topic-name{font-size:.78rem;font-weight:800;text-align:center;color:#2d2418}' +
      '.qa-btn-publish{width:100%;padding:.9rem;border-radius:14px;background:#e25c2e;color:#fff;border:none;font-size:1rem;font-weight:800;cursor:pointer;margin-bottom:1rem;box-shadow:0 2px 8px rgba(226,92,46,.3)}' +
      '.qa-btn-publish:disabled{opacity:.35;cursor:not-allowed;box-shadow:none}' +
      '.qa-consejo{background:#fff;border-radius:14px;padding:.85rem 1rem;border:1px solid #e8e1da}' +
      '.qa-consejo-title{font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:#8a7e74;margin:0 0 .3rem}' +
      '.qa-consejo-text{font-size:.82rem;color:#5a4e44;line-height:1.55;margin:0}' +
      '.qa-empty{text-align:center;padding:3rem 1rem;color:#b0a89e}' +
      '.qa-empty-icon{font-size:2.5rem;margin-bottom:.75rem}' +
      '#qa-view-detail{padding:1rem 1rem 2rem}' +
      '.qa-det-status{display:flex;gap:.4rem;margin-bottom:.75rem;flex-wrap:wrap}' +
      '.qa-det-title{font-size:1.05rem;font-weight:800;color:#1a1a1a;line-height:1.4;margin-bottom:.75rem}' +
      '.qa-det-author{display:flex;align-items:center;gap:.55rem;margin-bottom:1rem;padding-bottom:1rem;border-bottom:1px solid #ece6de}' +
      '.qa-det-author-name{font-size:.85rem;font-weight:700;color:#2d2418}' +
      '.qa-det-time{font-size:.74rem;color:#b0a89e;margin-left:auto}' +
      '.qa-det-body{font-size:.9rem;color:#3d3028;line-height:1.65;margin-bottom:1rem}' +
      '.qa-det-actions{display:flex;gap:.6rem;margin-bottom:1.5rem}' +
      '.qa-det-action-btn{display:inline-flex;align-items:center;gap:.4rem;padding:.55rem 1.1rem;border-radius:10px;font-size:.84rem;font-weight:700;cursor:pointer;border:1.5px solid #e0d8d0;background:#fff;color:#4a3f36;transition:all .18s}' +
      '.qa-det-action-btn.liked{color:#e25c2e;border-color:#e25c2e;background:#fff7f5}' +
      '.qa-det-action-btn svg{width:14px;height:14px}' +
      '.qa-best-label{font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.6px;color:#1b4332;display:flex;align-items:center;gap:.3rem;margin-bottom:.6rem}' +
      '.qa-best-label svg{width:13px;height:13px}' +
      '.qa-best-card{background:#1b4332;border-radius:14px;padding:1rem;margin-bottom:1rem}' +
      '.qa-best-badge{display:inline-flex;align-items:center;gap:.35rem;background:rgba(255,255,255,.15);border-radius:20px;padding:.3rem .75rem;font-size:.73rem;font-weight:700;color:#fff;margin-bottom:.65rem}' +
      '.qa-best-body{font-size:.88rem;color:#dcfce7;line-height:1.6;margin-bottom:.75rem}' +
      '.qa-best-footer{display:flex;align-items:center;gap:.5rem}' +
      '.qa-best-author{font-size:.77rem;font-weight:700;color:rgba(255,255,255,.75)}' +
      '.qa-best-likes{font-size:.76rem;color:rgba(255,255,255,.55);margin-left:auto;display:flex;align-items:center;gap:.25rem}' +
      '.qa-others-label{font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.6px;color:#8a7e74;margin:.75rem 0 .5rem}' +
      '.qa-answer-card{background:#fff;border-radius:14px;padding:.9rem 1rem;margin-bottom:.65rem;box-shadow:0 1px 4px rgba(0,0,0,.06)}' +
      '.qa-answer-body{font-size:.88rem;color:#2d2418;line-height:1.6;margin-bottom:.65rem}' +
      '.qa-answer-footer{display:flex;align-items:center;gap:.5rem}' +
      '.qa-answer-author{font-size:.76rem;color:#8a7e74;font-weight:600}' +
      '.qa-answer-like-btn{margin-left:auto;background:none;border:none;font-size:.78rem;color:#b0a89e;font-weight:700;cursor:pointer;padding:.2rem .4rem}' +
      '.qa-answer-like-btn.active{color:#e25c2e}' +
      '.qa-accept-btn{background:none;border:1px solid #1b4332;border-radius:8px;padding:.22rem .65rem;font-size:.74rem;font-weight:700;color:#1b4332;cursor:pointer}' +
      '.qa-post-answer{margin-top:1.25rem;background:#fff;border-radius:14px;padding:1rem;box-shadow:0 1px 4px rgba(0,0,0,.06)}' +
      '.qa-post-answer-label{font-size:.88rem;font-weight:800;color:#2d2418;margin-bottom:.5rem}' +
      '.qa-post-answer-input{width:100%;box-sizing:border-box;padding:.7rem .9rem;border:1.5px solid #e8e1da;border-radius:10px;font-size:.86rem;background:#f9f6f2;color:#1a1a1a;outline:none;resize:vertical;min-height:80px;font-family:inherit}' +
      '.qa-post-answer-input:focus{border-color:#1b4332;background:#fff}' +
      '.qa-post-answer-btn{margin-top:.5rem;width:100%;padding:.7rem;background:#e25c2e;color:#fff;border:none;border-radius:10px;font-size:.9rem;font-weight:800;cursor:pointer}' +
      /* Tarjeta pregunta detalle */
      '.qa-det-q-card{background:#fff;border-radius:16px;padding:1.25rem;margin-bottom:1rem;box-shadow:0 1px 5px rgba(0,0,0,.07)}' +
      '.qa-det-status{display:flex;gap:.4rem;margin-bottom:.65rem;flex-wrap:wrap}' +
      '.qa-det-title{font-size:1.08rem;font-weight:800;color:#1a1a1a;line-height:1.4;margin-bottom:.8rem}' +
      '.qa-det-author{display:flex;align-items:center;gap:.55rem;margin-bottom:.9rem}' +
      '.qa-det-author-info{}' +
      '.qa-det-author-name{font-size:.88rem;font-weight:700;color:#2d2418}' +
      '.qa-det-time{font-size:.74rem;color:#b0a89e;margin-top:.05rem}' +
      '.qa-det-body{font-size:.9rem;color:#3d3028;line-height:1.7;margin-bottom:1rem}' +
      '.qa-det-actions{display:flex;align-items:center;gap:.5rem;padding-top:.25rem}' +
      '.qa-det-action-btn{display:inline-flex;align-items:center;gap:.4rem;padding:.52rem 1rem;border-radius:10px;font-size:.84rem;font-weight:700;cursor:pointer;border:1.5px solid #e0d8d0;background:#fff;color:#4a3f36;transition:all .18s}' +
      '.qa-det-action-btn:hover{border-color:#1b4332;color:#1b4332}' +
      '.qa-det-action-btn svg{width:14px;height:14px}' +
      '.qa-det-like-btn{display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;border-radius:10px;border:1.5px solid #e0d8d0;background:#fff;cursor:pointer;transition:all .18s;color:#4a3f36}' +
      '.qa-det-like-btn svg{width:16px;height:16px}' +
      '.qa-det-like-btn.liked{color:#e25c2e;border-color:#e25c2e;background:#fff7f5}' +
      '.qa-det-trash-btn{display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;border-radius:10px;border:1.5px solid #fecaca;background:#fff5f5;cursor:pointer;color:#dc2626;margin-left:auto;transition:all .18s}' +
      '.qa-det-trash-btn:hover{background:#fee2e2;border-color:#dc2626}' +
      /* Respuestas mejoradas */
      '.qa-answer-header{display:flex;align-items:center;gap:.5rem;margin-bottom:.65rem}' +
      '.qa-answer-author-info{}' +
      '.qa-answer-author-name{font-size:.84rem;font-weight:700;color:#2d2418}' +
      '.qa-answer-time{font-size:.73rem;color:#b0a89e}' +
      /* Sidebar compartido */
      '.qa-sidebar-card{background:#fff;border-radius:14px;padding:1rem 1.1rem;box-shadow:0 1px 4px rgba(0,0,0,.06)}' +
      '.qa-sidebar-card-title{font-size:.7rem;font-weight:800;text-transform:uppercase;letter-spacing:.7px;color:#1b4332;margin-bottom:.7rem}' +
      '.qa-sidebar-stats{display:flex;flex-direction:column;gap:.45rem}' +
      '.qa-sidebar-stat{display:flex;align-items:center;gap:.45rem;font-size:.83rem;color:#4a3f36;font-weight:500}' +
      '.qa-sidebar-stat svg{width:14px;height:14px;color:#8a7e74;flex-shrink:0}' +
      '.qa-tips-list{margin:.3rem 0 0;padding-left:1.1rem;font-size:.82rem;color:#5a4e44;line-height:1.7}' +
      /* Layout nueva pregunta móvil */
      '.qa-nq-layout{padding:1rem 1rem 2.5rem}' +
      '.qa-nq-sidebar{display:none}' +
      /* Top-bar: brand y logo comparten el centro, mhdr-actions siempre visible a la derecha */
      '#screen-qa .mob-hdr{display:flex;align-items:center;gap:.5rem}' +
      '#screen-qa .mob-hdr .qa-hdr-brand{flex:1;min-width:0;overflow:hidden}' +
      '#screen-qa .mob-hdr .mhdr-logo{flex:1;display:flex;justify-content:center}' +
      '#screen-qa .mob-hdr .mhdr-actions{flex-shrink:0;margin-left:auto;display:flex;align-items:center;gap:.3rem}' +
      '@media(min-width:1025px){' +
        '#screen-qa .scroll-area{padding:0}' +
        /* Feed hero */
        '.qa-hero-card{border-radius:16px;margin:1.5rem 1.5rem 1rem;padding:1.75rem 2rem;display:grid;grid-template-columns:1fr auto;grid-template-rows:auto auto;gap:.75rem 1.5rem;align-items:start}' +
        '.qa-hero-title{font-size:1.35rem;grid-column:1/2;grid-row:1;margin:0}' +
        '.qa-search-row{grid-column:1/2;grid-row:2;padding:0}' +
        '.qa-cta-btn-wrap{grid-column:2/3;grid-row:1/3;display:flex;align-items:center;padding:0}' +
        '.qa-cta-btn{width:auto;white-space:nowrap;padding:.85rem 1.75rem}' +
        '.qa-feed-controls{padding:0 1.5rem}' +
        '.filters-label{padding:.5rem 0 .25rem}' +
        '.filters-row{padding:.4rem 0 .6rem}' +
        '.sort-row{padding:.2rem 0 .65rem}' +
        '.qa-list-wrap{padding:0 1.5rem 2rem}' +
        '.qa-cards-wrap{display:grid;grid-template-columns:repeat(3,1fr);gap:.85rem;align-items:start}' +
        '.qa-card{margin-bottom:0}' +
        /* Nueva pregunta — 2 columnas */
        '.qa-nq-layout{display:grid;grid-template-columns:1fr 300px;gap:1.5rem;align-items:start;padding:1.5rem 1.5rem 3rem;max-width:none}' +
        '.qa-nq-main{}' +
        '.qa-nq-sidebar{display:block}' +
        '.qa-topic-grid{grid-template-columns:repeat(3,1fr)}' +
        /* Detalle — 2 columnas */
        '#qa-view-detail{padding:1.5rem 1.5rem 3rem;max-width:none}' +
        '.qa-det-layout{display:grid;grid-template-columns:1fr 280px;gap:1.5rem;align-items:start}' +
        '.qa-det-main{}' +
        '.qa-det-sidebar{display:block}' +
        '.qa-det-q-card{padding:1.5rem}' +
        '.qa-det-title{font-size:1.2rem}' +
      '}';
    var s = document.createElement('style');
    s.id = 'vk-qa-styles';
    s.textContent = css;
    document.head.appendChild(s);
  }

  function _tok(){ return (window.ST && ST.token) ? ST.token : ''; }
  function _apiURL(path){ return (window.C && C.API_BASE ? C.API_BASE : 'https://vidakushala.com/wp-json') + path; }

  function _relTime(iso){
    if(!iso) return '';
    var d = new Date(iso), now = new Date(), diff = Math.floor((now-d)/1000);
    if(diff < 60) return 'hace un momento';
    if(diff < 3600) return 'hace '+Math.floor(diff/60)+' min';
    if(diff < 86400) return 'hace '+Math.floor(diff/3600)+' h';
    if(diff < 604800) return 'hace '+Math.floor(diff/86400)+' dias';
    return d.toLocaleDateString('es',{day:'numeric',month:'short'});
  }

  function _catLabel(slug){
    var m={salud:'Salud General',nutricion:'Nutricion',cursos:'Cursos',mente:'Bienestar Mental',ejercicio:'Ejercicio',casos:'Casos',otro:'Otro'};
    return m[slug]||slug||'General';
  }

  var _aColors=['#2d6a4f','#1b4332','#457b9d','#6d4c8e','#c05621','#b5451b','#2c7da0','#4a4e69'];
  function _avatarColor(n){ if(!n) return _aColors[0]; var c=0; for(var i=0;i<n.length;i++) c+=n.charCodeAt(i); return _aColors[c%_aColors.length]; }
  function _initials(n){ if(!n) return '?'; var p=n.trim().split(' '); return p.length>=2?(p[0][0]+p[1][0]).toUpperCase():n.substring(0,2).toUpperCase(); }
  function _avatar(name, size){
    size=size||26; var fs=Math.round(size*0.36);
    return '<span class="qa-avatar" style="width:'+size+'px;height:'+size+'px;font-size:'+fs+'px;background:'+_avatarColor(name)+'">'+_initials(name)+'</span>';
  }

  function mobBack(){
    var d=document.getElementById('qa-view-detail'), nq=document.getElementById('qa-view-new');
    if((d&&d.style.display!=='none')||(nq&&nq.style.display!=='none')){ showFeed(); return; }
    goto('home');
  }
  function showFeed(){
    _s('qa-view-feed');_h('qa-view-detail');_h('qa-view-new');
    _s('qa-hdr-feed');_h('qa-hdr-detail');_h('qa-hdr-new');
    _s('qa-hdr-logo');_h('qa-hdr-brand');
  }
  function showNew(){
    _s('qa-view-new');_h('qa-view-feed');_h('qa-view-detail');
    _h('qa-hdr-feed');_h('qa-hdr-detail');_s('qa-hdr-new');
    _h('qa-hdr-logo');_s('qa-hdr-brand');
    var t=document.getElementById('qa-nq-title'),b=document.getElementById('qa-nq-body'),p=document.getElementById('qa-btn-publish');
    if(t)t.value=''; if(b)b.value=''; if(p)p.disabled=true;
    _qaSelectedTopic='';
    document.querySelectorAll('.qa-topic-tile').forEach(function(el){el.classList.remove('selected');});
    updateCharCount();
  }
  function _s(id){var e=document.getElementById(id);if(e)e.style.display='';}
  function _h(id){var e=document.getElementById(id);if(e)e.style.display='none';}

  function loadFeed(){
    _injectStyles(); showFeed();
    var wrap=document.getElementById('qa-list-wrap');
    if(wrap) wrap.innerHTML='<div class="spinner-wrap"><div class="spinner"></div>Cargando preguntas...</div>';
    fetch(_apiURL('/vk/v1/qa/questions'),{headers:{'X-VK-Token':_tok()}})
    .then(function(r){return r.json();})
    .then(function(data){
      _qaData=Array.isArray(data)?data:(data.questions||[]);
      _applyFilter(); _renderFeed();
    })
    .catch(function(e){
      console.error('[QA]',e);
      if(wrap) wrap.innerHTML='<div class="qa-empty"><div class="qa-empty-icon">&#x1F4AC;</div><p>No se pudieron cargar las preguntas.</p></div>';
    });
  }

  function _applyFilter(){
    var q=((document.getElementById('qa-search-input')||{}).value||'').trim().toLowerCase();
    var f=_qaFilter;
    _qaFiltered=_qaData.filter(function(item){
      if(f==='waiting'  && item.answer_count>0) return false;
      if(f==='resolved' && !item.has_accepted)  return false;
      if(f==='teacher'  && !item.teacher_answered) return false;
      if(f==='none'     && item.answer_count>0) return false;
      if(q && item.title.toLowerCase().indexOf(q)===-1 && (item.excerpt||'').toLowerCase().indexOf(q)===-1) return false;
      return true;
    });
    if(_qaSort==='popular') _qaFiltered.sort(function(a,b){return(b.likes||0)-(a.likes||0);});
    else _qaFiltered.sort(function(a,b){return new Date(b.date)-new Date(a.date);});
    var cnt=document.getElementById('qa-stats-count');
    if(cnt) cnt.innerHTML='<strong>'+_qaFiltered.length+'</strong> preguntas';
  }

  function _statusBadge(item){
    if(item.has_accepted) return '<span class="qa-badge qa-badge-resolved"><span class="qa-badge-dot"></span>Resuelta</span>';
    if(item.answer_count===0) return '<span class="qa-badge qa-badge-none"><span class="qa-badge-dot"></span>Sin respuestas</span>';
    return '<span class="qa-badge qa-badge-waiting"><span class="qa-badge-dot"></span>Esperando respuesta</span>';
  }

  function _renderFeed(){
    var wrap=document.getElementById('qa-list-wrap');
    if(!wrap) return;
    if(!_qaFiltered.length){
      wrap.innerHTML='<div class="qa-empty"><div class="qa-empty-icon">&#x1F4AC;</div><p>No hay preguntas que coincidan.</p></div>';
      return;
    }
    var h='<div class="qa-cards-wrap">';
    _qaFiltered.forEach(function(item){
      var bdg=_statusBadge(item);
      if(item.teacher_answered) bdg+='<span class="qa-badge qa-badge-teacher">&#9733; Profesor</span>';
      h+='<div class="qa-card" onclick="vkQA.openQuestion('+item.id+')">';
      h+='<div class="qa-card-badges-row">'+bdg+'</div>';
      h+='<div class="qa-card-title">'+_esc(item.title)+'</div>';
      if(item.excerpt) h+='<div class="qa-card-excerpt">'+_esc(item.excerpt)+'</div>';
      h+='<div class="qa-card-footer">';
      h+=_avatar(item.author,26);
      h+='<span class="qa-author-name">'+_esc(item.author||'')+'</span>';
      h+='<span class="qa-footer-sep">&#xB7;</span>';
      h+='<span class="qa-footer-meta">'+_relTime(item.date)+'</span>';
      h+='<span class="qa-footer-sep">&#xB7;</span>';
      h+='<span class="qa-footer-meta"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>'+(item.answer_count||0)+'</span>';
      if(item.views){h+='<span class="qa-footer-sep">&#xB7;</span><span class="qa-footer-meta"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>'+item.views+'</span>';}
      h+='</div></div>';
    });
    h+='</div>';
    wrap.innerHTML=h;
  }

  function setFilter(f,btn){
    _qaFilter=f;
    document.querySelectorAll('#qa-filter-bar .chip').forEach(function(c){c.classList.remove('active');});
    if(btn) btn.classList.add('active');
    _applyFilter(); _renderFeed();
  }
  function filterFeed(){ _applyFilter(); _renderFeed(); }
  function toggleSort(btn){
    _qaSort=_qaSort==='recent'?'popular':'recent';
    if(btn) btn.querySelector('span').textContent=_qaSort==='recent'?'Mas recientes':'Mas populares';
    _applyFilter(); _renderFeed();
  }

  function openQuestion(id){
    var wrap=document.getElementById('qa-view-detail');
    if(wrap) wrap.innerHTML='<div class="spinner-wrap"><div class="spinner"></div>Cargando...</div>';
    _h('qa-view-feed');_h('qa-view-new');_s('qa-view-detail');
    _h('qa-hdr-feed');_s('qa-hdr-detail');_h('qa-hdr-new');
    _h('qa-hdr-logo');_s('qa-hdr-brand');
    fetch(_apiURL('/vk/v1/qa/questions/'+id),{headers:{'X-VK-Token':_tok()}})
    .then(function(r){return r.json();})
    .then(function(data){renderDetail(data);})
    .catch(function(e){console.error('[QA]',e);if(wrap)wrap.innerHTML='<div class="qa-empty">Error al cargar.</div>';});
  }

  function renderDetail(q){
    var wrap=document.getElementById('qa-view-detail'); if(!wrap)return;
    var catEl=document.getElementById('qa-hdr-cat-label'); if(catEl)catEl.textContent=_catLabel(q.category);

    var statusB=q.has_accepted?'<span class="qa-badge qa-badge-resolved"><span class="qa-badge-dot"></span>Resuelta</span>':'';
    var best=null, others=[];
    (q.answers||[]).forEach(function(a){ if(a.is_accepted&&!best)best=a; else others.push(a); });

    var likedQ=q.user_liked;
    var replyBtn='<button class="qa-det-action-btn" onclick="document.getElementById(\'qa-answer-input-'+q.id+'\').focus()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>Responder</button>';
    var likeBtn='<button class="qa-det-like-btn'+(likedQ?' liked':'')+'" id="qa-likeq-'+q.id+'" onclick="vkQA.likeQuestion('+q.id+',this)"><svg viewBox="0 0 24 24" fill="'+(likedQ?'currentColor':'none')+'" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg></button>';

    /* Tarjeta de la pregunta */
    var qCard=
      '<div class="qa-det-q-card">' +
        (statusB?'<div class="qa-det-status">'+statusB+'</div>':'')+
        '<div class="qa-det-title">'+_esc(q.title)+'</div>'+
        '<div class="qa-det-author">'+
          _avatar(q.author,36)+
          '<div class="qa-det-author-info"><div class="qa-det-author-name">'+_esc(q.author)+'</div><div class="qa-det-time">'+_relTime(q.date)+'</div></div>'+
        '</div>'+
        '<div class="qa-det-body">'+_esc(q.content)+'</div>'+
        '<div class="qa-det-actions">'+replyBtn+likeBtn+(q.can_delete?'<button class="qa-det-trash-btn" title="Enviar a papelera" onclick="vkQA.deleteQuestion('+q.id+',this)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="17" height="17"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg></button>':'')+
        '</div>'+
      '</div>';

    /* Mejor respuesta */
    var bestH='';
    if(best){
      bestH='<div class="qa-best-label"><svg viewBox="0 0 24 24" fill="currentColor" width="13" height="13"><path d="M12 2l2.4 6.6H21l-5.4 4.2 2 6.5L12 15.4l-5.6 3.9 2-6.5L3 8.6h6.6z"/></svg>MEJOR RESPUESTA</div>'+
        '<div class="qa-best-card">'+
          '<div class="qa-best-badge"><svg viewBox="0 0 24 24" fill="currentColor" width="11" height="11"><path d="M12 2l2.4 6.6H21l-5.4 4.2 2 6.5L12 15.4l-5.6 3.9 2-6.5L3 8.6h6.6z"/></svg>Respuesta recomendada por el profesor</div>'+
          '<div class="qa-best-body">'+_esc(best.content)+'</div>'+
          '<div class="qa-best-footer">'+
            _avatar(best.author,30)+
            '<span class="qa-best-author">'+_esc(best.author)+(best.is_teacher?' (Profesor)':'')+'</span>'+
            '<span class="qa-best-likes"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>'+(best.likes||0)+' les sirvio</span>'+
          '</div>'+
        '</div>';
    }

    /* Otras respuestas */
    var othH='';
    if(others.length){
      othH='<div class="qa-others-label">OTRAS RESPUESTAS ('+others.length+')</div>';
      others.forEach(function(a){
        var la=a.user_liked?' active':'';
        othH+='<div class="qa-answer-card">'+
          '<div class="qa-answer-header">'+
            _avatar(a.author,32)+
            '<div class="qa-answer-author-info">'+
              '<div class="qa-answer-author-name">'+_esc(a.author)+'</div>'+
              '<div class="qa-answer-time">'+_relTime(a.date)+'</div>'+
            '</div>'+
          '</div>'+
          '<div class="qa-answer-body">'+_esc(a.content)+'</div>'+
          '<div class="qa-answer-footer">'+
            (q.can_accept&&!a.is_accepted?'<button class="qa-accept-btn" onclick="vkQA.acceptAnswer('+a.id+','+q.id+',this)">Aceptar</button>':'')+
            '<button class="qa-answer-like-btn'+la+'" onclick="vkQA.likeAnswer('+a.id+',this)">&#9829; '+(a.likes||0)+'</button>'+
          '</div>'+
        '</div>';
      });
    }

    var noAns=!best&&!others.length?'<div class="qa-empty" style="padding:1.5rem 0"><p>Se el primero en responder.</p></div>':'';

    /* Sidebar desktop */
    var sidebar=
      '<div class="qa-det-sidebar">'+
        '<div class="qa-sidebar-card">'+
          '<div class="qa-sidebar-card-title">SOBRE ESTA PREGUNTA</div>'+
          '<div class="qa-sidebar-stats">'+
            '<div class="qa-sidebar-stat">'+
              '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>'+
              (q.answers||[]).length+' respuestas'+
            '</div>'+
            (q.views?'<div class="qa-sidebar-stat"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>'+q.views+' vistas</div>':'')+
            (q.category?'<div class="qa-sidebar-stat"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>Tema: '+_catLabel(q.category)+'</div>':'')+
            '<div class="qa-sidebar-stat"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>Publicada '+_relTime(q.date)+'</div>'+
          '</div>'+
        '</div>'+
      '</div>';

    /* Layout final */
    wrap.innerHTML=
      '<div class="qa-det-layout">'+
        '<div class="qa-det-main">'+
          qCard+bestH+othH+noAns+
          '<div class="qa-post-answer"><div class="qa-post-answer-label">Tu respuesta</div>'+
          '<textarea class="qa-post-answer-input" id="qa-answer-input-'+q.id+'" placeholder="Escribe tu respuesta..."></textarea>'+
          '<button class="qa-post-answer-btn" onclick="vkQA.postAnswer('+q.id+')">Publicar respuesta</button></div>'+
        '</div>'+
        sidebar+
      '</div>';
  }

  function likeQuestion(id,btn){
    fetch(_apiURL('/vk/v1/qa/questions/'+id+'/like'),{method:'POST',headers:{'X-VK-Token':_tok()}})
    .then(function(r){return r.json();})
    .then(function(d){
      if(btn){
        btn.classList.toggle('active',d.liked);
        var svg=btn.querySelector('svg'); if(svg)svg.setAttribute('fill',d.liked?'currentColor':'none');
        var nodes=btn.childNodes;nodes[nodes.length-1].textContent=(d.likes||0)+' Me gusta';
      }
    }).catch(function(){});
  }
  function likeAnswer(id,btn){
    fetch(_apiURL('/vk/v1/qa/answers/'+id+'/like'),{method:'POST',headers:{'X-VK-Token':_tok()}})
    .then(function(r){return r.json();})
    .then(function(d){if(btn){btn.classList.toggle('active',d.liked);btn.innerHTML='&#9829; '+(d.likes||0);}})
    .catch(function(){});
  }
  function acceptAnswer(answerId,questionId,btn){
    fetch(_apiURL('/vk/v1/qa/answers/'+answerId+'/accept'),{method:'POST',headers:{'X-VK-Token':_tok()}})
    .then(function(r){return r.json();})
    .then(function(d){if(d.ok){showToast('Respuesta aceptada');openQuestion(questionId);}})
    .catch(function(){});
  }
  function postAnswer(questionId){
    var inp=document.getElementById('qa-answer-input-'+questionId);
    if(!inp||!inp.value.trim()){showToast('Escribe tu respuesta primero');return;}
    var body=inp.value.trim();
    fetch(_apiURL('/vk/v1/qa/questions/'+questionId+'/answers'),{
      method:'POST',headers:{'Content-Type':'application/json','X-VK-Token':_tok()},
      body:JSON.stringify({content:body})
    })
    .then(function(r){return r.json();})
    .then(function(d){if(d.ok||d.id){showToast('Respuesta publicada');openQuestion(questionId);}else showToast('Error: '+(d.message||'No se pudo publicar'));})
    .catch(function(){showToast('Error de red');});
  }
  function selectTopic(btn){
    document.querySelectorAll('.qa-topic-tile').forEach(function(e){e.classList.remove('selected');});
    btn.classList.add('selected'); _qaSelectedTopic=btn.dataset.topic||''; checkPublish();
  }
  function updateCharCount(){
    var inp=document.getElementById('qa-nq-title'),cnt=document.getElementById('qa-title-count');
    if(!inp||!cnt)return; cnt.textContent=inp.value.length+' / 120';
  }
  function checkPublish(){
    var t=(document.getElementById('qa-nq-title')||{}).value||'';
    var btn=document.getElementById('qa-btn-publish');
    if(btn) btn.disabled=!(t.trim().length>=5&&_qaSelectedTopic);
  }
  function publishQuestion(){
    var title=((document.getElementById('qa-nq-title')||{}).value||'').trim();
    var body=((document.getElementById('qa-nq-body')||{}).value||'').trim();
    if(!title||!_qaSelectedTopic){showToast('Completa el titulo y elige un tema');return;}
    var btn=document.getElementById('qa-btn-publish');
    if(btn){btn.disabled=true;btn.textContent='Publicando...';}
    fetch(_apiURL('/vk/v1/qa/questions'),{
      method:'POST',headers:{'Content-Type':'application/json','X-VK-Token':_tok()},
      body:JSON.stringify({title:title,content:body,category:_qaSelectedTopic})
    })
    .then(function(r){return r.json();})
    .then(function(d){
      if(d.ok||d.id){showToast('Pregunta publicada');loadFeed();}
      else{showToast('Error: '+(d.message||'No se pudo publicar'));if(btn){btn.disabled=false;btn.textContent='Publicar pregunta';}}
    })
    .catch(function(){showToast('Error de red');if(btn){btn.disabled=false;btn.textContent='Publicar pregunta';}});
  }
  function deleteQuestion(id,btn){
    if(!confirm('¿Enviar esta pregunta a la papelera?')) return;
    if(btn){btn.disabled=true;}
    fetch(_apiURL('/vk/v1/qa/questions/'+id),{method:'DELETE',headers:{'X-VK-Token':_tok()}})
    .then(function(r){return r.json();})
    .then(function(d){
      if(d.ok){showToast('Pregunta enviada a papelera');loadFeed();}
      else{showToast('Error: '+(d.message||'No se pudo eliminar'));if(btn)btn.disabled=false;}
    })
    .catch(function(){showToast('Error de red');if(btn)btn.disabled=false;});
  }
  function _esc(s){if(!s)return'';return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}

  return{mobBack:mobBack,showFeed:showFeed,showNew:showNew,loadFeed:loadFeed,
    openQuestion:openQuestion,renderDetail:renderDetail,setFilter:setFilter,
    filterFeed:filterFeed,toggleSort:toggleSort,updateCharCount:updateCharCount,
    checkPublish:checkPublish,publishQuestion:publishQuestion,selectTopic:selectTopic,
    postAnswer:postAnswer,likeQuestion:likeQuestion,likeAnswer:likeAnswer,acceptAnswer:acceptAnswer,
    deleteQuestion:deleteQuestion};
})();

/* ══════════════════════════════════════════
   MANEJO GLOBAL DE ERRORES Y CONEXIÓN
══════════════════════════════════════════ */
(function(){
  function _ensureOfflineBanner(){
    if(document.getElementById('vk-offline-banner'))return;
    var b=document.createElement('div');
    b.id='vk-offline-banner';
    b.innerHTML='<span>&#9888; Sin conexión — revisa tu internet</span><button onclick="location.reload()">Reintentar</button>';
    b.style.cssText='display:none;position:fixed;top:0;left:0;right:0;z-index:99999;background:#1b4332;color:#fff;font-family:\'DM Sans\',sans-serif;font-size:.875rem;font-weight:600;padding:.6rem 1rem;text-align:center;align-items:center;justify-content:center;gap:1rem';
    var btn=b.querySelector('button');
    btn.style.cssText='background:#e25c2e;color:#fff;border:none;padding:.3rem .85rem;border-radius:8px;font-weight:700;cursor:pointer;font-size:.8rem';
    document.body.appendChild(b);
  }
  function _showOffline(){
    _ensureOfflineBanner();
    var b=document.getElementById('vk-offline-banner');
    if(b)b.style.display='flex';
  }
  function _hideOffline(){
    var b=document.getElementById('vk-offline-banner');
    if(b)b.style.display='none';
  }
  window.addEventListener('offline',_showOffline);
  window.addEventListener('online',function(){
    _hideOffline();
    if(typeof showToast==='function')showToast('&#10003; Conexión restaurada');
  });
  if(!navigator.onLine)_showOffline();

  window.onerror=function(msg,src,line,col,err){
    console.error('[VK-global]',msg,src,line,col,err);
    if(typeof showToast==='function'){
      var isNet=!navigator.onLine||String(msg).toLowerCase().includes('fetch');
      showToast(isNet?'&#9888; Sin conexión a internet':'&#9888; Ocurrió un error — recarga si el problema continúa');
    }
    return false;
  };

  window.addEventListener('unhandledrejection',function(ev){
    var reason=ev.reason||{};
    var msg=reason.message||String(reason);
    console.error('[VK-promise]',msg);
    if(typeof showToast==='function'){
      var isNet=!navigator.onLine||msg==='Failed to fetch'||msg.includes('NetworkError');
      showToast(isNet?'&#9888; Sin conexión — verifica tu red':'&#9888; Error inesperado — intenta de nuevo');
    }
  });
})();
