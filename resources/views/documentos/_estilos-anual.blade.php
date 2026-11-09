{{-- Ruta final · Carta. Portada, mapa mensual, membresía y primer paso. --}}
<style>
.doc:has(.ruta-final-doc){padding:0}
.ruta-final-doc{
  --rf-magenta:#E91665; --rf-uva:#330A28; --rf-tinta:#20191E; --rf-texto:#5A4B55;
  --rf-gris:#82747D; --rf-gris-2:#9A8F97; --rf-borde:#ECE6EA; --rf-borde-2:#DCD3DA;
  --rf-fondo:#FAF7FA; --rf-pill:#F4ECF1; --rf-papel:#FFF;
  --rf-display:'Anton','Arial Narrow',Impact,sans-serif; --rf-cond:'Antonio','Arial Narrow',sans-serif;
  --rf-body:'Poppins','Segoe UI',Arial,sans-serif;
  font-family:var(--rf-body);color:var(--rf-tinta);font-size:12px;line-height:1.5;
  -webkit-print-color-adjust:exact;print-color-adjust:exact;
}
.ruta-final-page{width:215.9mm!important;height:279.4mm;min-height:0!important;margin:0!important;padding:0;
  position:relative;overflow:hidden;page-break-after:always;break-after:page;display:flex;flex-direction:column;
  background:#FFF;box-shadow:none!important}
.ruta-final-page:last-child{page-break-after:auto;break-after:auto}
.ruta-final-page.rf-interior{padding:6mm 13mm 5mm}
.ruta-final-page .enc{display:flex;align-items:center;justify-content:space-between;gap:22px;flex:none;
  border-bottom:1px solid var(--rf-borde);padding:0 0 16px;margin:0 0 24px;height:45px;
  color:var(--rf-uva);font-family:var(--rf-cond);font-size:10px;font-weight:700;letter-spacing:.16em;text-transform:uppercase}
.ruta-final-page .pie{margin-top:auto;padding:10px 0 0;border-top:0;display:flex;justify-content:space-between;
  align-items:baseline;gap:16px;flex:none;font-size:8px;color:var(--rf-gris);letter-spacing:0}
.ruta-final-page .pie span:last-child{font-family:var(--rf-cond);color:var(--rf-uva);font-size:10px;white-space:nowrap}
.rf-marca{display:flex;align-items:center;gap:10px;flex:none}
.rf-marca img{width:40px;height:46px;object-fit:contain}
.rf-marca span{font-family:var(--rf-cond);font-weight:700;font-size:17px;letter-spacing:.045em;line-height:1.15;color:var(--rf-uva);text-transform:uppercase}
.enc .rf-marca{gap:7px;letter-spacing:0}
.enc .rf-marca img{width:25px;height:29px}
.enc .rf-marca span{font-size:10px}
.rf-progreso{display:flex;align-items:center;gap:10px;margin-right:auto}
.rf-progreso>span{font-size:8px;color:var(--rf-gris);white-space:nowrap}
.rf-progreso>div{display:flex;gap:3px}
.rf-progreso div span{width:17px;text-align:center;font-size:8px;color:#B5AAB1;letter-spacing:0}
.rf-progreso i{display:block;height:5px;border-radius:4px;background:var(--rf-borde);margin-bottom:2px}
.rf-progreso .activo{color:var(--rf-uva)}
.rf-progreso .activo i{background:var(--rf-uva)}
.rf-progreso .activo.primero i{background:var(--rf-magenta)}
/* Portada en dos paneles. Los doce hexágonos salen de los meses del snapshot. */
.rf-portada{flex-direction:row}
.rf-portada-cuerpo{width:56%;flex:none;display:flex;flex-direction:column;padding:13mm 10mm 10mm 13mm}
.rf-para{margin-top:80px;font-family:var(--rf-cond);font-size:10px;letter-spacing:.2em;color:var(--rf-magenta);text-transform:uppercase}
.rf-para::before,.rf-eyebrow::before{content:"";display:inline-block;width:17px;height:2px;background:var(--rf-magenta);vertical-align:middle;margin-right:9px}
.rf-nom{font-family:var(--rf-display);font-size:33px;line-height:1.15;color:var(--rf-uva);overflow-wrap:anywhere;margin-top:7px}
.rf-nom.largo{font-size:25px}
.rf-puesto{font-size:11px;line-height:1.5;color:var(--rf-gris);overflow-wrap:anywhere}
.rf-activa{align-self:flex-start;margin-top:36px;padding:3px 13px;border-radius:20px;background:linear-gradient(100deg,#E91665,#800E64);
  color:#FFF;font-family:var(--rf-cond);font-weight:700;font-size:10px;letter-spacing:.15em;text-transform:uppercase;box-shadow:0 3px 12px #800E6433}
.rf-portada h1{font-family:var(--rf-display);font-weight:400;font-size:78px;line-height:1.04;text-transform:uppercase;color:var(--rf-uva);margin:14px 0 12px;letter-spacing:-.025em}
.rf-portada h1 em{font-style:normal;color:var(--rf-magenta)}
.rf-bajada{font-size:11.5px;line-height:1.75;color:var(--rf-texto);margin:0}
.rf-ficha{display:grid;grid-template-columns:1fr 1fr;margin-top:28px;border-top:1px solid var(--rf-borde)}
.rf-ficha div{padding:12px 8px 12px 11px;border-bottom:1px solid var(--rf-borde);position:relative;min-width:0}
.rf-ficha div:nth-child(even){border-left:1px solid var(--rf-borde)}
.rf-ficha div::before{content:"";position:absolute;width:3px;top:13px;bottom:13px;left:0;border-radius:2px;background:var(--rf-magenta)}
.rf-ficha div:nth-child(even)::before{left:8px}
.rf-ficha div:nth-child(even){padding-left:19px}
.rf-ficha span{display:block;font-family:var(--rf-cond);font-size:9px;letter-spacing:.18em;text-transform:uppercase;color:var(--rf-gris)}
.rf-ficha b{display:block;font-family:var(--rf-cond);font-size:20px;font-weight:700;color:var(--rf-uva);line-height:1.4;overflow-wrap:anywhere}
.rf-arranque{display:flex;align-items:center;gap:16px;background:linear-gradient(110deg,#4B103D,#2A0820);color:#FFF;border-radius:14px;
  padding:14px 20px;margin-top:22px;box-shadow:0 8px 12px #2A082033}
.rf-arranque i{width:37px;height:42px;display:grid;place-items:center;flex:none;font-family:var(--rf-display);font-size:22px;font-style:normal;
  background:var(--rf-magenta);clip-path:polygon(50% 0,100% 25%,100% 75%,50% 100%,0 75%,0 25%)}
.rf-arranque span{display:block;font-family:var(--rf-cond);font-size:9px;letter-spacing:.2em;text-transform:uppercase;color:#D6BACD}
.rf-arranque b{font-family:var(--rf-cond);font-size:22px;line-height:1.3;display:block;overflow-wrap:anywhere}
.rf-firma-portada{margin-top:auto;padding-top:11px;border-top:1px solid var(--rf-borde);display:flex;justify-content:space-between;gap:12px;font-size:8px;color:var(--rf-gris)}
.rf-firma-portada span:last-child{font-family:var(--rf-cond);color:var(--rf-uva);white-space:nowrap}
.rf-vistazo{width:44%;padding:14mm 8mm 9mm;position:relative;overflow:hidden;color:#FFF;background:linear-gradient(150deg,#430D36,#23071D)}
.rf-vistazo .rf-eyebrow{color:#DB9BBF;margin:0 0 8px;font-size:9px}
.rf-vistazo .rf-eyebrow::before{display:none}
.rf-vistazo h2{font-family:var(--rf-cond);font-size:29px;margin:0 0 40px;line-height:1.2;font-weight:700;color:#FFF}
.rf-panales{position:relative;z-index:1;margin-bottom:30px}
.rf-panal-fila{display:flex;align-items:center;margin-top:-4px;gap:6px}
.rf-panal-etapa{width:51px;flex:none}
.rf-panal-etapa b{display:block;font-family:var(--rf-cond);font-size:9px;letter-spacing:.15em;text-transform:uppercase;font-weight:400}
.rf-panal-etapa span{display:block;font-size:7px;color:#D4B9CB;white-space:nowrap}
.rf-panal-meses{display:flex;gap:3px}
.rf-panal-fila:nth-child(even) .rf-panal-meses{transform:translateX(31px)}
.rf-hex{width:62px;height:72px;position:relative;background:#B58EA9;clip-path:polygon(50% 0,100% 25%,100% 75%,50% 100%,0 75%,0 25%)}
.rf-hex>div{position:absolute;inset:1.5px;background:#451536;clip-path:inherit;display:flex;flex-direction:column;align-items:center;justify-content:center}
.rf-hex.primero,.rf-hex.primero>div{background:var(--rf-magenta)}
.rf-hex b{font-family:var(--rf-cond);font-size:24px;line-height:1.3}
.rf-hex span{font-size:7px;letter-spacing:.04em}
.rf-resumen-etapas{position:relative;z-index:1}
.rf-resumen-etapas>div{display:flex;align-items:center;gap:15px;padding:12px 0;border-top:1px solid #FFFFFF40}
.rf-resumen-etapas>div:last-child{border-bottom:1px solid #FFFFFF40}
.rf-resumen-etapas b{font-family:var(--rf-display);font-weight:400;font-size:18px;color:#D66AAB}
.rf-resumen-etapas span{flex:1;font-size:9px;line-height:1.55}
.rf-resumen-etapas strong{font-family:var(--rf-cond);font-size:16px;text-align:right;white-space:nowrap}
.rf-resumen-etapas small{display:block;font-family:var(--rf-body);font-size:7px;color:#CEB3C6;font-weight:400}
.rf-marca-agua{position:absolute;opacity:.09;width:270px;right:-80px;bottom:40px}
.rf-vistazo-pie{position:absolute;bottom:9mm;left:8mm;font-size:8px;color:#D6BACD}
/* Mapa: un contenedor por mes, cursos separados por reglas y etiqueta de área. */
.rf-eyebrow{font-family:var(--rf-cond);font-size:11px;letter-spacing:.2em;color:var(--rf-magenta);text-transform:uppercase;margin:0 0 5px}
.rf-h2{font-family:var(--rf-display);font-size:40px;line-height:1.1;text-transform:uppercase;color:var(--rf-uva);margin-bottom:9px}
.rf-objetivo,.rf-intro{font-size:11.5px;line-height:1.65;color:var(--rf-texto);margin:0 0 14px;overflow-wrap:anywhere}
.rf-leer{display:flex;gap:10px;margin-bottom:18px}
.rf-leer span{flex:1;display:flex;align-items:center;gap:9px;padding:8px 10px;border:1px solid var(--rf-borde);border-radius:10px;background:var(--rf-fondo);font-size:9px;line-height:1.4}
.rf-leer b{font-family:var(--rf-display);font-size:23px;color:var(--rf-magenta);font-weight:400}
.rf-etapa{display:flex;align-items:center;gap:13px;padding:11px 15px;border-radius:10px;background:linear-gradient(110deg,#4B103D,#2A0820);color:#FFF;margin-top:2px}
.rf-etapa .n{font-family:var(--rf-cond);font-weight:700;font-size:10px;letter-spacing:.12em;text-transform:uppercase;white-space:nowrap;background:#A31264;padding:3px 9px;border-radius:12px;flex:none}
.rf-etapa>b{font-family:var(--rf-cond);font-size:16px;line-height:1.25;text-transform:uppercase;letter-spacing:.015em;font-weight:700;min-width:0}
.rf-etapa .m{font-family:var(--rf-cond);font-size:10px;letter-spacing:.1em;text-transform:uppercase;margin-left:auto;white-space:nowrap;flex:none}
.rf-mapa-pagina>*{flex-shrink:0}
.rf-camino{padding:12px 0 2px}
.rf-mes{display:grid;grid-template-columns:34px minmax(0,1fr);gap:13px;position:relative;padding-bottom:13px;break-inside:avoid}
.rf-mes::before{content:"";position:absolute;left:17px;top:30px;bottom:0;border-left:2px dotted #DDD1D9}
.rf-mes.ultimo::before{display:none}
.rf-nodo{position:relative;z-index:1;margin-top:6px;width:34px;height:38px;display:grid;place-items:center;background:var(--rf-magenta);color:var(--rf-uva);isolation:isolate;font-family:var(--rf-cond);font-weight:700;font-size:20px;clip-path:polygon(50% 0,100% 25%,100% 75%,50% 100%,0 75%,0 25%)}
.rf-nodo::after{content:"";position:absolute;z-index:-1;inset:2px;background:#FFF;clip-path:inherit}
.rf-mes.inicio .rf-nodo{color:#FFF}
.rf-mes.inicio .rf-nodo::after{display:none}
.rf-mes.libre .rf-nodo{background:var(--rf-borde-2);color:var(--rf-gris)}
.rf-mes-tarjeta{border:1px solid var(--rf-borde);border-radius:13px;overflow:hidden;box-shadow:0 2px 4px #330A2808}
.rf-mes.inicio .rf-mes-tarjeta{border-color:#FF8EBB;box-shadow:0 0 0 3px #FFE4EF}
.rf-mes-cab{display:flex;align-items:center;gap:10px;padding:9px 14px;background:var(--rf-fondo);border-bottom:1px solid var(--rf-borde)}
.rf-mes-cab>b{font-family:var(--rf-cond);font-weight:700;font-size:17px;text-transform:uppercase;color:var(--rf-uva);white-space:nowrap}
.rf-mes-cab .cal{font-size:9px;color:var(--rf-gris);white-space:nowrap}
.rf-mes-cab .carga{margin-left:auto;font-size:9px;color:var(--rf-gris);white-space:nowrap}
.rf-tag-aqui{font-family:var(--rf-cond);font-weight:700;font-size:8px;letter-spacing:.1em;text-transform:uppercase;background:linear-gradient(100deg,#E91665,#800E64);color:#FFF;border-radius:12px;padding:2px 9px;white-space:nowrap}
.rf-curso{display:flex;align-items:center;gap:10px;padding:9px 14px;border-bottom:1px solid #F0ECF0;min-height:40px}
.rf-curso:last-child{border-bottom:0}
.rf-curso .t{flex:1;min-width:0;line-height:1.5}
.rf-curso .t b{font-size:11px;font-weight:600;overflow-wrap:anywhere}
.rf-curso .t span{display:inline-block;font-size:8px;color:#800E64;background:var(--rf-pill);padding:1px 8px;border-radius:12px;margin-left:7px;vertical-align:middle}
.rf-curso .h{font-family:var(--rf-cond);font-size:17px;font-weight:700;color:var(--rf-uva);white-space:nowrap}
.rf-curso.sigue{background:#FDFCFE}
.rf-curso.sigue .t b{color:var(--rf-gris)}
.rf-curso.sigue .h{font-size:12px;color:var(--rf-gris)}
.rf-libre-txt{padding:15px;font-size:11px;color:var(--rf-gris)}
.rf-meta{display:flex;gap:15px;align-items:center;background:linear-gradient(110deg,#4B103D,#2A0820);color:#FFF;border-radius:12px;padding:15px 20px;margin-top:9px}
.rf-meta i{font-style:normal;font-family:var(--rf-cond);font-size:11px;letter-spacing:.12em;color:#F499C2}
.rf-meta b{display:block;font-family:var(--rf-cond);font-size:21px;text-transform:uppercase}
.rf-meta-titulo{flex:1}
.rf-meta-cifras{display:flex;gap:24px;align-items:center}
.rf-meta-cifras strong{display:block;font-family:var(--rf-cond);font-size:32px;line-height:1.1}
.rf-meta-cifras span{display:block;font-size:9px;color:#D6BACD;line-height:1.4}
.rf-flex-nota{font-size:10px;line-height:1.65;color:var(--rf-gris);padding:12px;border:1px solid var(--rf-borde);border-radius:10px;margin:12px 0 0}
/* Inventario: las horas de los niveles incluidos nunca se vuelven a sumar. */
.rf-inv{columns:2;column-gap:28px}
.rf-inv-grupo{break-inside:avoid;margin-bottom:19px}
.rf-inv h3{display:flex;align-items:baseline;justify-content:space-between;gap:8px;font-family:var(--rf-cond);font-size:14px;text-transform:uppercase;color:var(--rf-uva);background:var(--rf-pill);border:0;border-left:3px solid var(--rf-magenta);border-radius:0 6px 6px 0;padding:7px 9px;margin:0 0 5px;line-height:1.35}
.rf-inv h3 small{font-family:var(--rf-body);font-size:8px;text-transform:none;white-space:nowrap;font-weight:400;color:var(--rf-gris)}
.rf-inv ul{margin:0;padding:0}
.rf-inv li{list-style:none;display:flex;align-items:center;gap:7px;padding:6px 0;border-bottom:1px solid #F3EFF2;font-size:10px;line-height:1.4}
.rf-inv-dot{width:8px;height:9px;flex:none;clip-path:polygon(50% 0,100% 25%,100% 75%,50% 100%,0 75%,0 25%);background:var(--rf-borde-2)}
.rf-inv-dot.planned{background:var(--rf-magenta)}
.rf-inv li.plan{font-weight:600;color:var(--rf-uva)}
.rf-inv li.sub{color:var(--rf-gris);padding-left:17px;font-size:9px}
.rf-inv-arrow{width:8px;flex:none}
.rf-inv-name{flex:1;min-width:0;overflow-wrap:anywhere}
.rf-inv li .x{margin-left:auto;color:var(--rf-gris);font-weight:400;white-space:nowrap;font-size:10px}
.rf-leyenda{display:flex;gap:18px;font-size:9px;color:var(--rf-gris);margin:8px 0}
.rf-leyenda-arriba{margin:0 0 13px}
.rf-leyenda span{display:flex;align-items:center;gap:6px}
.rf-leyenda i{width:8px;height:9px;clip-path:polygon(50% 0,100% 25%,100% 75%,50% 100%,0 75%,0 25%);background:var(--rf-borde-2)}
.rf-leyenda i.p{background:var(--rf-magenta)}
/* Cierre */
.rf-pasos{margin:12px 0 26px}
.rf-pasos>div{display:flex;gap:18px;align-items:center;border-bottom:1px solid var(--rf-borde);padding:17px 0;font-size:12px}
.rf-pasos b{font-family:var(--rf-cond);font-size:23px;font-weight:700;line-height:1;width:39px;height:44px;flex:none;color:#FFF;display:grid;place-items:center;background:var(--rf-magenta);clip-path:polygon(50% 0,100% 25%,100% 75%,50% 100%,0 75%,0 25%)}
.rf-dc3{display:flex;gap:19px;align-items:center;background:var(--rf-fondo);border:1px solid var(--rf-borde);border-radius:14px;padding:20px;margin-bottom:17px}
.rf-dc3 .sello{width:68px;height:77px;flex:none;background:linear-gradient(120deg,#E91665,#800E64);clip-path:polygon(50% 0,100% 25%,100% 75%,50% 100%,0 75%,0 25%);display:flex;flex-direction:column;align-items:center;justify-content:center;font-family:var(--rf-cond);font-weight:700;font-size:22px;color:#FFF}
.rf-dc3 .sello{border-radius:0;box-shadow:none}
.rf-dc3 .sello::before{display:none}
.rf-dc3 .sello small{font-size:9px;letter-spacing:.2em}
.rf-dc3 h3{font-family:var(--rf-cond);font-size:11px;color:var(--rf-magenta);letter-spacing:.17em;text-transform:uppercase;margin:0 0 8px}
.rf-dc3 p{font-size:11px;line-height:1.7;color:var(--rf-texto);margin:0}
.rf-asesor{padding:14px 18px;border:1px solid var(--rf-borde);border-radius:10px}
.rf-asesor h4{font-family:var(--rf-cond);font-size:13px;letter-spacing:.13em;text-transform:uppercase;color:var(--rf-uva);margin:0 0 5px}
.rf-asesor p,.rf-asesor .tel{font-size:11px;margin:0;color:var(--rf-texto)}
.rf-firma-asesor{display:flex;align-items:center;gap:12px;margin-top:20px;font-size:11px;color:var(--rf-uva)}
.rf-firma-asesor i{font-style:normal;font-family:var(--rf-cond);font-size:15px;font-weight:700}
.rf-firma-asesor b,.rf-firma-asesor span{display:block}
.rf-firma-asesor span{color:var(--rf-gris);font-size:9px}
.rf-cierre-marca{display:flex;align-items:center;justify-content:space-between;margin-top:auto;padding:25px 0 18px;border-top:1px solid var(--rf-borde)}
.rf-cierre-marca>span{font-family:var(--rf-cond);font-size:20px;letter-spacing:.16em;color:var(--rf-uva)}
.rf-cierre .pie{margin-top:0}
@media print{.doc:has(.ruta-final-doc){padding:0}.ruta-final-page{margin:0!important;box-shadow:none!important}}
@media screen and (max-width:820px){.doc:has(.ruta-final-doc){overflow-x:auto}.ruta-final-page{flex-shrink:0}}
</style>
