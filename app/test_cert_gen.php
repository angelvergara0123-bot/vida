<?php
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache');
?><!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>Test Cert</title>
<script src="vk-cert-renderer.js?v=<?php echo time(); ?>"></script>
</head>
<body style="background:#111;color:#0f0;font-family:monospace;padding:20px">
<h2 style="color:#ff0">Test Certificado</h2>
<div id="log" style="font-size:13px;margin-bottom:20px"></div>
<canvas id="test-canvas" style="max-width:100%;border:2px solid #0f0;display:none"></canvas>
<script>
var L=[];
function log(msg,color){color=color||'#0f0';L.push('<span style="color:'+color+'">'+msg+'</span>');document.getElementById('log').innerHTML=L.join('<br>');}

async function run(){
    // Forzar sin caché con timestamp
    var ts = Date.now();
    log('Llamando cert-theme sin caché...');
    
    try {
        var r = await fetch('https://vidakushala.com/wp-json/vk/v1/cert-theme?nocache='+ts, {
            cache: 'no-store',
            headers: {'Cache-Control': 'no-cache', 'Pragma': 'no-cache'}
        });
        var d = await r.json();
        var cfg = d.config;
        
        log('HTTP: ' + r.status);
        log('bg_image_url: ' + cfg.bg_image_url);
        log('bg_image_data: ' + (cfg.bg_image_data ? '✅ (' + cfg.bg_image_data.length + ' chars)' : '❌ NO'), cfg.bg_image_data?'#0f0':'#f00');
        log('header_color: ' + cfg.header_color + ' (debe ser #6f102a)');
        log('name_color: ' + cfg.name_color + ' (debe ser #104170)');
        
        // Si sigue recibiendo versión vieja, mostrar aviso
        if (cfg.header_color === '#23a2b3') {
            log('⚠️ RECIBIENDO VERSIÓN CACHEADA - limpia caché en SiteGround', '#ff0');
            log('Ve a: SiteGround → Speed → Caching → Flush Cache', '#ff0');
            return;
        }
        
        log('\nCargando imagen...');
        var bgImg = null;
        
        if (cfg.bg_image_data) {
            bgImg = await new Promise(function(res){
                var img=new Image();
                img.onload=function(){res(img);};
                img.onerror=function(){res(null);};
                img.src=cfg.bg_image_data;
            });
            log(bgImg ? '✅ Imagen base64 OK: '+bgImg.width+'x'+bgImg.height : '❌ Falló base64', bgImg?'#0f0':'#f00');
        }
        
        log('\nRenderizando...');
        var canvas = document.getElementById('test-canvas');
        canvas.style.display = 'block';
        
        await vkRenderCertCanvas(canvas, cfg, {
            student_name: 'Juan Pérez García',
            course_title: 'Curso de Ejemplo',
            cert_date: '31 de mayo de 2026',
            cert_id: 'TEST123ABC',
            validation_url: 'https://vidakushala.com',
            bg_img: bgImg
        });
        log('✅ Listo - ver imagen abajo', '#0ff');
        
    } catch(e) {
        log('❌ Error: ' + e.message, '#f00');
    }
}
run();
</script>
</body></html>
