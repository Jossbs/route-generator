{{-- La hoja de estilos de marca, portada tal cual desde design/pdf (gen_b.py::CSS).
     Los tokens del :root son los medidos en academy.dpsys.com.mx el 2026-09-09.
     Va en línea a propósito: el render corre en Chromium headless, sin build de Vite. --}}
<style>
@page{size:Letter;margin:0}

:root{
  /* ---- tokens de marca, medidos en academy.dpsys.com.mx ---- */
  --vino:#2A0820;  --ink:#330A28;  --magenta:#800E64;
  --rosa:#E91E63;  --rosa-h:#D4145A;
  --papel:#FFFFFF; --crema:#FBF8FA; --tinte:#F4ECF1;
  --borde:#ECE6EA; --borde-2:#DCD3DA; --gris:#7A6C74; --texto:#1A1A1A;
  --ambar:#F5A623;
  --display:'Anton','Arial Narrow',sans-serif;
  --cond:'Antonio','Arial Narrow',sans-serif;
  --body:'Poppins','Segoe UI',system-ui,sans-serif;
  --sombra:0 2px 6px rgba(46,8,34,.08), 0 18px 40px rgba(128,14,100,.18);
  --acento:var(--rosa);           /* lo pisa cada especialización */
}

*{box-sizing:border-box}
html{font-size:12px}
body{
  margin:0; background:#E9E4EE; color:var(--texto);
  font-family:var(--body); font-size:1rem; line-height:1.5;
  -webkit-print-color-adjust:exact; print-color-adjust:exact;
  -webkit-font-smoothing:antialiased;
}

/* ============================ la hoja ============================ */
.hoja{
  width:216mm; min-height:279mm; background:var(--papel);
  margin:0 auto 9mm; padding:13mm 17mm 12mm; position:relative;
  box-shadow:var(--sombra); overflow:hidden;
  display:flex; flex-direction:column;
}
.hoja > .cuerpo{flex:1 1 auto; min-height:0}

/* encabezado y pie de las hojas interiores */
.enc{display:flex; align-items:center; gap:9px; padding-bottom:7px;
     border-bottom:1px solid var(--borde); margin-bottom:7mm}
.enc img{height:20px; width:auto; display:block}
.enc .marca{font-family:var(--cond); font-weight:700; font-size:1.05rem;
            letter-spacing:.14em; text-transform:uppercase; color:var(--ink)}
.enc .der{margin-left:auto; font-family:var(--cond); font-weight:600; font-size:.95rem;
          letter-spacing:.13em; text-transform:uppercase; color:var(--gris)}

.pie{margin-top:6mm; padding-top:6px; border-top:1px solid var(--borde);
     display:flex; align-items:baseline; gap:10px;
     font-size:.78rem; color:#9A8E96; letter-spacing:.02em}
.pie b{font-weight:600; color:var(--gris)}
.pie .num{margin-left:auto; font-family:var(--cond); font-weight:700; font-size:1.1rem;
          letter-spacing:.1em; color:var(--ink); font-variant-numeric:tabular-nums}
/* El total, en tono menor: explica que la portada es la 01 sin numerarla. */
.pie .num i{font-style:normal; font-weight:600; font-size:.82em; color:#9A8E96}

/* ============================ portada ============================ */
.portada{padding:0; color:#fff; background:var(--vino)}
.portada .fondo{position:absolute; inset:0;
  background:
    radial-gradient(120% 95% at 10% -12%, rgba(233,30,99,.46), transparent 58%),
    radial-gradient(95% 75% at 108% 104%, rgba(128,14,100,.62), transparent 62%),
    linear-gradient(158deg,#330A28 0%,#240718 62%,#1B0413 100%);
}
.portada .fondo::after{content:''; position:absolute; inset:0; opacity:.5;
  background:repeating-linear-gradient(115deg, rgba(255,255,255,.035) 0 1px, transparent 1px 46px)}
.portada .inner{position:relative; z-index:1; flex:1; display:flex; flex-direction:column;
                padding:16mm 17mm 14mm}
.pt-agua{position:absolute; top:-4%; right:-19%; width:78%; opacity:.048; z-index:0;
         transform:rotate(-8deg); pointer-events:none}

.pt-logo{height:40px; width:auto; display:block; margin-bottom:auto}
.pt-tag{font-family:var(--cond); font-weight:600; font-size:1rem; letter-spacing:.22em;
        text-transform:uppercase; color:rgba(255,255,255,.62); margin-top:9px}

.pt-eyebrow{display:inline-flex; align-items:center; gap:8px; align-self:flex-start;
  font-family:var(--cond); font-weight:700; font-size:1.02rem; letter-spacing:.2em;
  text-transform:uppercase; color:#fff; padding:5px 13px; border-radius:999px;
  background:linear-gradient(100deg,#E91E63,#800E64);
  box-shadow:0 6px 18px rgba(233,30,99,.35); margin-bottom:14px}

.pt-para{font-size:1.05rem; color:rgba(255,255,255,.66); letter-spacing:.02em; margin:0 0 6px}
.pt-nombre{font-family:var(--cond); font-weight:700; font-size:2.5rem; line-height:1.05;
           color:#fff; letter-spacing:.005em; margin:0 0 3px}
.pt-ocup{font-size:1.02rem; color:rgba(255,255,255,.6); margin:0 0 22px}

.pt-h1{font-family:var(--display); font-weight:400; font-size:5.1rem; line-height:.94;
       letter-spacing:.005em; text-transform:uppercase; margin:0 0 14px; color:#fff}
.pt-h1 .fina{display:block; font-family:var(--cond); font-weight:400; font-size:.36em;
             line-height:1.2; letter-spacing:.02em; text-transform:none;
             color:rgba(255,255,255,.72); margin-top:12px; max-width:31em}
.pt-regla{width:74px; height:4px; border-radius:999px; margin:0 0 18px;
          background:linear-gradient(90deg,#E91E63,#DD3CA7)}

.pt-cifras{display:flex; gap:0; margin-top:26px; border-top:1px solid rgba(255,255,255,.18);
           padding-top:16px}
.pt-cifras > div{flex:1; padding-right:14px}
.pt-cifras > div + div{border-left:1px solid rgba(255,255,255,.16); padding-left:16px}
.pt-cifras b{display:block; font-family:var(--display); font-weight:400; font-size:2.9rem;
             line-height:1; color:#fff; letter-spacing:.01em}
.pt-cifras b i{font-style:normal; font-size:.42em; color:var(--rosa); margin-left:4px;
               vertical-align:.55em; font-family:var(--cond); font-weight:700;
               letter-spacing:.1em}
.pt-cifras span{display:block; font-family:var(--cond); font-weight:600; font-size:.92rem;
                letter-spacing:.16em; text-transform:uppercase;
                color:rgba(255,255,255,.55); margin-top:6px}

.pt-pie{display:flex; align-items:flex-end; gap:16px; margin-top:26px;
        font-size:.86rem; color:rgba(255,255,255,.5)}
.pt-pie .der{margin-left:auto; text-align:right}
/* La portada sí lleva folio. Sin él, la primera hoja numerada era la «02» y la
   numeración se leía desfasada, como si el documento hubiera perdido una página. */
.pt-pie .folio{font-family:var(--cond); font-weight:700; font-size:1rem; letter-spacing:.1em;
  color:rgba(255,255,255,.72); font-variant-numeric:tabular-nums}
.pt-pie .folio i{font-style:normal; font-weight:600; font-size:.82em; opacity:.7}

/* ======================= títulos de sección ======================= */
.h-sec{margin:0 0 3px}
.h-sec .kicker{font-family:var(--cond); font-weight:700; font-size:.98rem; letter-spacing:.2em;
               text-transform:uppercase; color:var(--rosa); display:block; margin-bottom:5px}
.h-sec h2{font-family:var(--display); font-weight:400; font-size:2.6rem; line-height:1;
          letter-spacing:.01em; text-transform:uppercase; color:var(--ink); margin:0}
.entradilla{font-size:1.02rem; color:var(--gris); line-height:1.55; max-width:44em; margin:7px 0 4mm}

/* ==================== bloque de especialización ==================== */
.bloque{margin-bottom:4mm; break-inside:avoid}
.bloque:last-child{margin-bottom:0}
.bl-enc{display:flex; align-items:baseline; gap:11px; padding-bottom:6px;
        border-bottom:2px solid var(--ink); margin-bottom:2px}
.bl-barra{width:5px; height:1.5em; align-self:center; border-radius:999px;
           background:var(--acento); flex:none}
.bl-tit{flex:1; min-width:0; display:flex; align-items:baseline; gap:9px; flex-wrap:wrap}
.bl-rol{display:inline-block; font-family:var(--cond); font-weight:700; font-size:.84rem;
        letter-spacing:.17em; text-transform:uppercase; padding:2px 8px; border-radius:999px;
        color:#fff; background:var(--ink); transform:translateY(-2px); white-space:nowrap}
.bl-rol.compl{background:transparent; color:var(--gris); border:1px solid var(--borde-2)}
.bl-rol.ancla{background:linear-gradient(100deg,#E91E63,#800E64)}
.bl-tit h3{font-family:var(--cond); font-weight:700; font-size:1.95rem; line-height:1.05;
           letter-spacing:.005em; color:var(--ink); margin:0}
/* La duración, en el mismo formato en todo el documento.
   Antes cada sitio la escribía a su manera —«3 PROGRAMAS 30 H» en el encabezado de
   la especialización, «16 h» suelto en la fila del curso, versalitas con
   interletraje en el inventario— y en el PDF se leían como tres formatos del mismo
   dato. Una píldora, minúsculas, cifras tabulares y sin partir el renglón. */
.dur{flex:none; align-self:center; display:inline-block; white-space:nowrap;
     font-family:var(--cond); font-weight:600; font-size:.98rem; letter-spacing:.03em;
     color:var(--gris); background:var(--tinte); border-radius:999px; padding:1px 9px;
     font-variant-numeric:tabular-nums}
.bl-enc > .dur{font-size:1.02rem; color:var(--ink)}
/* Bajo el título de un grupo del inventario, donde no hay renglón que compartir. */
.dur.bajo{display:block; width:max-content; margin-top:2px; font-size:.84rem; padding:0 7px}

/* fila de curso */
/* Alto mínimo: un curso sin frase de resultado ocupaba la mitad que sus vecinos,
   y dos seguidos al final de un bloque se apelotonaban contra el margen con los
   números amontonados a la izquierda. Con el mínimo, cada curso es un renglón de
   la ruta, lleve descripción o no. */
.curso{display:flex; align-items:center; gap:10px; padding:3px 0; min-height:33px;
       border-bottom:1px solid var(--borde); position:relative}
.curso:last-child{border-bottom:0}
/* El hilo que une los pasos.
   Una lista numerada se lee como un índice; lo que el documento vende es una ruta,
   y el hilo vertical entre los números es lo que la hace ver como tal sin ocupar
   una sola línea más de hoja. No alcanza al primero por arriba ni al último por
   abajo: una ruta empieza y termina en algún lado. */
.cursos{position:relative}
.cursos::before{content:''; position:absolute; left:11px; top:16px; bottom:16px; width:1px;
  background:var(--acento); opacity:.28}
.cu-n{flex:none; align-self:center; width:22px; line-height:1.2; position:relative; z-index:1;
      font-family:var(--cond); font-weight:700; font-size:1.15rem; text-align:center;
      color:var(--acento); letter-spacing:.04em; font-variant-numeric:tabular-nums;
      background:var(--papel); padding:2px 0}
.cu-n.punto{display:flex; align-items:center; height:1.35em}
.cu-n.punto::before{content:''; width:6px; height:6px; border-radius:2px; background:var(--acento);
  margin-left:7px}
.cu-cuerpo{flex:1; min-width:0}
.cu-nom{font-weight:600; font-size:1.1rem; color:var(--texto); letter-spacing:.005em;
        line-height:1.3}
.cu-desc{font-size:.9rem; color:var(--gris); line-height:1.4; margin-top:1px}
.cu-meta{flex:none; align-self:center; text-align:right; font-size:.86rem; color:var(--gris);
         white-space:nowrap; line-height:1.35}
.cu-meta .dur{align-self:auto}
/* La nota corre como texto, no como fila: un <b> dentro no puede partir la frase
   en columnas. El asterisco cuelga del margen. */
.nota{display:block; position:relative; margin-top:7px; padding:7px 11px 7px 27px;
      border-radius:9px; background:var(--tinte); font-size:.88rem; color:var(--magenta);
      line-height:1.45}
.nota::before{content:'✳'; position:absolute; left:10px; top:7px; opacity:.7}
.nota b{font-weight:600}

/* ========================= panel de cifras ========================= */
.cifras{display:grid; grid-template-columns:repeat(3,1fr); gap:1px; background:var(--borde);
        border:1px solid var(--borde); border-radius:14px; overflow:hidden; margin-bottom:7mm}
.cifras > div{background:var(--crema); padding:14px 15px}
.cifras b{display:block; font-family:var(--display); font-weight:400; font-size:2.6rem;
          line-height:1; color:var(--ink)}
.cifras b i{font-style:normal; font-size:.4em; color:var(--rosa); margin-left:3px;
            vertical-align:.6em; font-family:var(--cond); font-weight:700; letter-spacing:.08em}
.cifras span{display:block; font-family:var(--cond); font-weight:600; font-size:.9rem;
             letter-spacing:.14em; text-transform:uppercase; color:var(--gris); margin-top:5px}
.cifras small{display:block; font-size:.82rem; color:var(--gris); line-height:1.4; margin-top:6px}

/* tarjeta destacada (credencial, cierre) */
.tarjeta{border:1px solid var(--borde); border-radius:16px; padding:16px 18px;
         background:var(--crema); margin-bottom:6mm}
.tarjeta.oscura{background:var(--vino); border-color:var(--vino); color:#fff; position:relative;
  overflow:hidden}
.tarjeta.oscura::before{content:''; position:absolute; inset:0;
  background:radial-gradient(110% 130% at 100% 0%, rgba(233,30,99,.34), transparent 60%)}
.tarjeta.oscura > *{position:relative}
.tarjeta h4{font-family:var(--cond); font-weight:700; font-size:1.5rem; letter-spacing:.02em;
            margin:0 0 6px; color:inherit}
.tarjeta p{margin:0; font-size:.98rem; line-height:1.6; color:var(--gris)}
.tarjeta.oscura p{color:rgba(255,255,255,.74)}
.tarjeta .kicker{font-family:var(--cond); font-weight:700; font-size:.9rem; letter-spacing:.18em;
                 text-transform:uppercase; color:var(--rosa); display:block; margin-bottom:6px}

/* ============================= firma ============================= */
.firma{display:flex; gap:14px; align-items:flex-start; border-top:2px solid var(--ink);
       padding-top:13px}
.firma .avatar{flex:none; width:46px; height:46px; border-radius:999px; color:#fff;
  background:linear-gradient(100deg,#E91E63,#800E64); display:flex; align-items:center;
  justify-content:center; font-family:var(--cond); font-weight:700; font-size:1.45rem;
  letter-spacing:.04em; box-shadow:0 6px 18px rgba(233,30,99,.35)}
.firma .logo{flex:none; width:46px; height:46px; display:block}
.firma .txt{flex:1; min-width:0}
.firma .nota-asesor{font-size:1.02rem; line-height:1.62; color:var(--texto); margin:0 0 9px}
.firma .quien{font-family:var(--cond); font-weight:700; font-size:1.35rem; color:var(--ink);
              letter-spacing:.01em}
.firma .rol{font-size:.9rem; color:var(--gris)}
.firma .tel{font-weight:600; color:var(--magenta)}

/* ============================ inventario ============================ */
.inv{column-count:3; column-gap:7mm}
.inv-grupo{break-inside:avoid; margin-bottom:5.5mm}
.inv-grupo h5{font-family:var(--cond); font-weight:700; font-size:1.15rem; line-height:1.12;
  margin:0 0 4px; padding-left:9px; border-left:4px solid var(--acento); color:var(--ink)}

.inv-grupo ul{list-style:none; margin:0; padding:0}
.inv-grupo li{display:flex; gap:5px; align-items:baseline; font-size:.83rem; line-height:1.42;
              color:var(--texto); padding:.5px 0}
.inv-grupo li .p{flex:none; width:7px; color:var(--acento); font-size:1.1em; line-height:1}
.inv-grupo li .n{flex:1; min-width:0}
.inv-grupo li .h{flex:none; color:var(--gris); font-variant-numeric:tabular-nums; font-size:.95em}
.inv-grupo li.sub{padding-left:12px; color:var(--gris)}
.inv-grupo li.sub .n{font-size:.95em}
.inv-grupo li.sub .h{font-style:italic; font-size:.88em}
.inv-pie{margin-top:5mm; padding-top:7px; border-top:1px solid var(--borde);
  font-size:.82rem; color:var(--gris); line-height:1.5}

/* ====================== marcadores de copy ====================== */
[data-copy]{position:relative}
body.marcar [data-copy]{outline:1.5px dashed var(--rosa); outline-offset:3px;
  background:rgba(233,30,99,.05); border-radius:3px}
body.marcar [data-copy][data-estado="borrador"]{outline-color:var(--ambar);
  background:rgba(245,166,35,.09)}
body.marcar [data-copy]::after{content:attr(data-copy); position:absolute; top:-9px; left:0;
  font-family:var(--body); font-size:8px; font-weight:600; letter-spacing:.06em; color:#fff;
  background:var(--rosa); padding:1px 5px; border-radius:999px; white-space:nowrap; z-index:5}
body.marcar [data-copy][data-estado="borrador"]::after{background:var(--ambar); color:var(--vino)}

/* ============================ barra (no imprime) ============================ */
.barra{position:sticky; top:0; z-index:40; display:flex; align-items:center; gap:10px;
  padding:9px 16px; background:rgba(42,8,32,.97); color:#fff;
  backdrop-filter:saturate(140%) blur(6px); font-size:12px}
.barra a,.barra button{font:inherit; font-family:var(--body); font-size:12px; font-weight:500;
  color:#fff; background:rgba(255,255,255,.11); border:1px solid rgba(255,255,255,.16);
  border-radius:8px; padding:7px 12px; cursor:pointer; text-decoration:none; line-height:1}
.barra button:hover,.barra a:hover{background:rgba(255,255,255,.2)}
.barra .pri{background:linear-gradient(100deg,#E91E63,#800E64); border-color:transparent;
  box-shadow:0 6px 18px rgba(233,30,99,.35)}
.barra .tit{font-family:var(--cond); font-weight:700; letter-spacing:.14em; text-transform:uppercase;
  font-size:12px}
.barra .sep{margin-left:auto}
.barra .aviso{color:rgba(255,255,255,.55); font-size:11px}
.doc{padding:9mm 0}

/* ============================ impresión ============================ */
@media print{
  body{background:#fff}
  .barra,.no-print{display:none !important}
  .doc{padding:0}
  .hoja{margin:0; box-shadow:none; break-after:page; page-break-after:always}
  .hoja:last-child{break-after:auto; page-break-after:auto}
  a{color:inherit; text-decoration:none}
}

/* ====================== pantalla chica: el enlace ====================== */
@media screen and (max-width:820px){
  html{font-size:16px}
  body{background:#fff}
  .doc{padding:0}
  .hoja{width:auto; min-height:0; margin:0; padding:26px 20px 30px; box-shadow:none;
        border-bottom:1px solid var(--borde)}
  .hoja:last-child{border-bottom:0}
  .portada{padding:0}
  .portada .inner{padding:26px 20px 30px}
  .portada .inner > div:first-child{margin-bottom:34px}
  .pt-eyebrow{margin-top:4px}
  .pt-h1{font-size:2.9rem}
  .pt-nombre{font-size:1.7rem}
  .pt-cifras{display:grid; grid-template-columns:1fr 1fr; gap:14px}
  .pt-cifras > div + div{border-left:0; padding-left:0}
  .pt-cifras > div:nth-child(n+3){border-top:1px solid rgba(255,255,255,.16); padding-top:12px}
  .pt-cifras b{font-size:2.1rem}
  .h-sec h2{font-size:1.9rem}
  .enc{margin-bottom:20px}
  .pie{display:none}
  .bl-enc > .dur{font-size:.9rem}
  .bl-enc{flex-wrap:wrap}
  .bl-tit h3{font-size:1.5rem}
  .curso{flex-wrap:wrap}
  .cu-meta{width:100%; text-align:left; padding-left:32px; margin-top:2px}
  .curso{min-height:0}
  .cursos::before{display:none}
  .cu-n{background:transparent}
  .cifras{grid-template-columns:1fr}
  .inv{column-count:1}
  .firma{flex-wrap:wrap}
  .barra{flex-wrap:wrap; gap:7px; padding:8px 12px}
  .barra .tit{width:100%}
}

/* ====================================================================
   Rediseño 2026-09-14 · lo que el documento necesitaba para persuadir
   ==================================================================== */

/* ---- portada: por qué esta ruta, con las palabras del propio lead ---- */
.pt-porque{margin:0 0 18px; padding:12px 15px 13px; border-radius:13px;
  border:1px solid rgba(255,255,255,.16); border-left:3px solid var(--rosa);
  background:rgba(255,255,255,.05)}
.pt-porque .k{display:block; font-family:var(--cond); font-weight:700; font-size:.86rem;
  letter-spacing:.18em; text-transform:uppercase; color:var(--rosa); margin-bottom:4px}
.pt-porque p{margin:0; font-size:1rem; line-height:1.55; color:rgba(255,255,255,.82)}
.pt-porque em{font-style:normal; color:#fff; font-weight:500}

/* ---- fila de curso: el nivel, que es lo que la vuelve una progresión ---- */
.cu-nivel{display:inline-block; margin-left:7px; padding:0 6px; border-radius:999px;
  font-family:var(--cond); font-weight:700; font-size:.74rem; letter-spacing:.14em;
  text-transform:uppercase; color:var(--gris); border:1px solid var(--borde-2);
  vertical-align:.1em; white-space:nowrap}
.cu-nivel.paquete{color:var(--magenta); border-color:#EFC4DF; background:var(--tinte)}
/* Un nivel absorbido no imprime horas: repetirlas es el doble conteo. */
.cu-meta .inc{display:block; font-family:var(--cond); font-weight:600; font-size:.82rem;
  letter-spacing:.12em; text-transform:uppercase; color:var(--magenta)}

/* ---- el alcance de la membresía: tu ruta es el arranque, no el techo ---- */
.alcance{border:1px solid var(--borde); border-radius:16px; overflow:hidden; margin-bottom:5mm;
  break-inside:avoid}
.alcance .cab{display:flex; align-items:center; gap:13px; padding:12px 16px;
  background:var(--crema); border-bottom:1px solid var(--borde)}
.alcance .cab h4{flex:1; min-width:0; font-family:var(--cond); font-weight:700; font-size:1.35rem;
  letter-spacing:.01em; color:var(--ink); margin:0}
.alcance .cab .c{display:flex; gap:18px; flex:none}
.alcance .cab .c div{text-align:right}
.alcance .cab .c b{display:block; font-family:var(--display); font-weight:400; font-size:1.75rem;
  line-height:1; color:var(--ink)}
.alcance .cab .c span{display:block; font-family:var(--cond); font-weight:600; font-size:.78rem;
  letter-spacing:.13em; text-transform:uppercase; color:var(--gris); margin-top:3px}
.alcance p{margin:0; padding:11px 16px 0; font-size:.95rem; line-height:1.55; color:var(--gris)}
.chips{display:flex; flex-wrap:wrap; gap:5px 6px; padding:10px 16px 13px}
.chips span{font-family:var(--cond); font-weight:600; font-size:.9rem; letter-spacing:.05em;
  color:var(--gris); border:1px solid var(--borde-2); border-radius:999px; padding:2px 9px;
  white-space:nowrap}
/* La especialización que el lead pidió va marcada: lo suyo primero, lo demás incluido. */
.chips span.tuya{color:#fff; border-color:transparent; background:linear-gradient(100deg,#E91E63,#800E64)}
.chips-pie{padding:0 16px 12px; font-size:.8rem; color:var(--gris); line-height:1.45}

/* ---- el siguiente paso: el documento no termina, devuelve la conversación ---- */
.cta{position:relative; overflow:hidden; display:flex; align-items:center; gap:15px;
  padding:14px 18px; border-radius:15px; background:var(--vino); color:#fff; margin-bottom:5mm}
.cta::before{content:''; position:absolute; inset:0;
  background:radial-gradient(120% 150% at 100% 0%, rgba(233,30,99,.4), transparent 62%)}
.cta > *{position:relative}
.cta .k{flex:none; font-family:var(--cond); font-weight:700; font-size:.9rem; letter-spacing:.2em;
  text-transform:uppercase; color:var(--rosa)}
.cta p{flex:1; min-width:0; margin:0; font-size:1.02rem; line-height:1.5; color:#fff}

/* ---- firma: el asesor con nombre y cara, que es de quien el lead se fía ---- */
.firma .nota-asesor{font-size:1rem; line-height:1.6; color:var(--texto); margin:0 0 8px}
/* En la hoja de cierre del anual la firma va al pie, como en un documento
   firmado, y no colgando a media hoja. Solo ahí: el flex no colapsa márgenes y en
   una hoja llena eso la desborda. */
.cuerpo.cierre{display:flex; flex-direction:column}
.cuerpo.cierre .firma{margin-top:auto}
@media screen and (max-width:820px){
  .cuerpo.cierre{display:block}
}


/* ---- continuación de sección: una hoja que sigue lo dice ---- */
.enc .cont{font-family:var(--body); font-weight:500; font-size:.84rem; letter-spacing:.02em;
  text-transform:none; color:var(--gris); opacity:.8}

@media screen and (max-width:820px){
  .alcance .cab{flex-wrap:wrap}
  .alcance .cab .c{width:100%; justify-content:flex-start; gap:26px}
  .alcance .cab .c div{text-align:left}
  .cta{flex-wrap:wrap; gap:8px}
  .cifras{grid-template-columns:1fr 1fr}
}

/* ====================================================================
   Revisión 2026-09-17 · lo que la lectura del documento pedía
   ==================================================================== */

/* ---- el objetivo: por qué esta ruta es de esta persona y no de otra ---- */
.objetivo{display:flex; gap:11px; align-items:flex-start; margin:8px 0 4mm; padding:11px 14px;
  border-radius:13px; background:var(--crema); border:1px solid var(--borde);
  border-left:3px solid var(--acento)}
.objetivo .k{flex:none; font-family:var(--cond); font-weight:700; font-size:.86rem;
  letter-spacing:.18em; text-transform:uppercase; color:var(--magenta); padding-top:2px}
.objetivo p{margin:0; font-size:1rem; line-height:1.55; color:var(--texto)}

/* ---- el sello de la credencial ----
   Es una insignia de DP SyS, no el escudo de la STPS: el documento afirma que la
   constancia es la DC-3 oficial ante la Secretaría del Trabajo —dato publicado,
   docs/ARQUITECTURA.md §6— sin vestirse con la identidad de una dependencia. */
.sello{flex:none; width:74px; height:74px; border-radius:999px; position:relative;
  display:flex; flex-direction:column; align-items:center; justify-content:center;
  color:#fff; background:linear-gradient(140deg,#E91E63,#800E64);
  box-shadow:0 8px 22px rgba(233,30,99,.34)}
.sello::before{content:''; position:absolute; inset:4px; border-radius:999px;
  border:1px dashed rgba(255,255,255,.55)}
.sello b{font-family:var(--display); font-weight:400; font-size:1.7rem; line-height:1;
  letter-spacing:.02em}
.sello span{font-family:var(--cond); font-weight:700; font-size:.68rem; letter-spacing:.17em;
  text-transform:uppercase; color:rgba(255,255,255,.85); margin-top:2px}
.tarjeta.oscura .cab-sello{display:flex; gap:15px; align-items:center}
.tarjeta.oscura .cab-sello > div{flex:1; min-width:0}

/* ---- el cierre: dos intenciones, dos botones ----
   Ninguno imprime precio. Abren la conversación con el asesor, que es donde el
   precio se dice y donde se pueden atender objeciones (docs/ARQUITECTURA.md §4.5). */
.cta{flex-wrap:wrap}
.cta .txt{flex:1; min-width:14em}
.cta-botones{display:flex; flex-wrap:wrap; gap:8px; width:100%}
.cta-botones a{display:inline-flex; align-items:center; gap:7px; text-decoration:none;
  font-family:var(--cond); font-weight:700; font-size:1.02rem; letter-spacing:.09em;
  text-transform:uppercase; padding:9px 16px; border-radius:999px; line-height:1;
  color:#fff; border:1px solid rgba(255,255,255,.28); background:rgba(255,255,255,.08)}
.cta-botones a.pri{background:linear-gradient(100deg,#E91E63,#800E64); border-color:transparent;
  box-shadow:0 6px 18px rgba(233,30,99,.4)}
.cta-botones a::before{content:''; flex:none; width:8px; height:8px; border-radius:2px;
  background:#fff; opacity:.9}
/* Impreso el enlace no se toca, así que el número va escrito. */
.cta-tel{width:100%; margin:0; font-size:.86rem; color:rgba(255,255,255,.62)}

@media screen and (max-width:820px){
  .objetivo{flex-direction:column; gap:5px}
  .cta-botones a{flex:1; justify-content:center}
  .sello{width:62px; height:62px}
}
/* ====================================================================
   Rediseño 2026-09-24 · ruta inicial de cuatro hojas y anual como expediente
   ==================================================================== */

/* ---- el punto de partida de la ruta ---- */
.curso.primero{background:linear-gradient(90deg,rgba(233,30,99,.07),transparent 70%)}
.cu-aqui{display:inline-block; margin-bottom:2px; padding:1px 7px; border-radius:4px;
  font-family:var(--cond); font-weight:700; font-size:.74rem; letter-spacing:.14em;
  text-transform:uppercase; color:#fff; background:var(--rosa)}
.ritmo{margin:5mm 0 0; padding-top:8px; border-top:1px solid var(--borde);
  font-size:.95rem; color:var(--gris)}

/* ---- quiénes somos: cifras, empresas, fotos y reseñas publicadas ---- */
.cifras.inst{margin-bottom:6mm}
.cifras.inst b{font-size:2.3rem}
.sub-sec{font-family:var(--cond); font-weight:700; font-size:.95rem; letter-spacing:.16em;
  text-transform:uppercase; color:var(--gris); margin:0 0 8px}
/* Monocromos: a color, quince logos compiten con la marca del documento. */
.logos{display:grid; grid-template-columns:repeat(5,1fr); gap:8px 12px; align-items:center;
  margin-bottom:6mm}
.logos span{height:30px; display:flex; align-items:center; justify-content:center;
  font-family:var(--cond); font-weight:600; font-size:.85rem; color:var(--gris); text-align:center}
.logos img{max-height:30px; max-width:100%; width:auto; height:auto; object-fit:contain;
  filter:grayscale(1); opacity:.62}
.fotos{display:grid; grid-template-columns:1.4fr 1fr 1fr; gap:6px}
.fotos img{width:100%; height:44mm; object-fit:cover; border-radius:8px; display:block}
.pie-fotos{margin:6px 0 6mm; font-size:.84rem; color:var(--gris)}
.resenas{display:grid; grid-template-columns:1fr 1fr; gap:10px}
.resenas figure{margin:0; padding:11px 14px; border-radius:12px; background:var(--crema);
  border:1px solid var(--borde); border-left:3px solid var(--rosa)}
.resenas blockquote{margin:0; font-size:.95rem; line-height:1.55; color:var(--texto)}
.resenas figcaption{margin-top:6px; font-family:var(--cond); font-weight:600; font-size:.85rem;
  letter-spacing:.08em; color:var(--gris)}

/* ---- anual: la portada es un expediente, no un cartel ---- */
.portada-anual{padding:0; background:var(--crema)}
.pa-banda{display:flex; align-items:center; justify-content:space-between;
  padding:9mm 17mm 8mm; background:var(--vino); color:#fff}
.pa-banda img{height:30px; width:auto; display:block}
.pa-banda span{font-family:var(--cond); font-weight:700; font-size:1rem; letter-spacing:.22em;
  text-transform:uppercase; color:rgba(255,255,255,.75)}
.pa-cuerpo{flex:1; display:flex; flex-direction:column; padding:16mm 17mm 12mm}
.pa-eyebrow{font-family:var(--cond); font-weight:700; font-size:1rem; letter-spacing:.2em;
  text-transform:uppercase; color:var(--magenta)}
.pa-nombre{font-family:var(--cond); font-weight:700; font-size:3.2rem; line-height:1.02;
  color:var(--vino); margin:10px 0 4px}
.pa-ocup{font-size:1.1rem; color:var(--gris); margin:0}
.pa-regla{width:64px; height:2px; background:var(--magenta); margin:10mm 0 8mm}
.pa-bajada{font-size:1.2rem; line-height:1.55; color:var(--texto); margin:0 0 10px; max-width:34em}
.pa-objetivo{font-size:1rem; line-height:1.6; color:var(--gris); margin:0; max-width:36em}
.pa-ficha{margin:auto 0 0; display:grid; grid-template-columns:1fr 1fr; border:1.5px solid var(--vino);
  background:var(--papel)}
.pa-ficha div{padding:11px 14px; border-bottom:1px solid var(--borde-2)}
.pa-ficha div:nth-child(odd){border-right:1px solid var(--borde-2)}
.pa-ficha div:nth-last-child(-n+2){border-bottom:0}
.pa-ficha dt{font-family:var(--cond); font-weight:600; font-size:.8rem; letter-spacing:.16em;
  text-transform:uppercase; color:var(--gris)}
.pa-ficha dd{margin:3px 0 0; font-family:var(--cond); font-weight:700; font-size:1.3rem;
  color:var(--vino); font-variant-numeric:tabular-nums}
.pa-pie{display:flex; gap:16px; margin-top:8mm; font-size:.86rem; color:var(--gris)}
.pa-pie .folio{margin-left:auto; font-family:var(--cond); font-weight:700; font-size:1rem;
  letter-spacing:.1em; color:var(--ink); font-variant-numeric:tabular-nums}
.pa-pie .folio i{font-style:normal; font-weight:600; font-size:.82em; color:#9A8E96}

/* En el anual el acento es el magenta: tono de expediente, sin el rosa de venta. */
.hoja.anual .h-sec .kicker{color:var(--magenta)}
.hoja.anual .h-sec h2{font-family:var(--cond); font-weight:700; text-transform:none;
  font-size:2.3rem; letter-spacing:.005em}

/* ---- anual: las cifras del plan, las únicas del documento ---- */
.kpis{display:grid; grid-template-columns:repeat(4,1fr); border:1.5px solid var(--vino);
  margin:12px 0 6mm}
.kpis div{padding:8px 12px; border-right:1px solid var(--borde-2)}
.kpis div:last-child{border-right:0}
.kpis b{display:block; font-family:var(--cond); font-weight:700; font-size:1.7rem; line-height:1.1;
  color:var(--vino); font-variant-numeric:tabular-nums}
.kpis span{font-family:var(--cond); font-weight:600; font-size:.8rem; letter-spacing:.14em;
  text-transform:uppercase; color:var(--gris)}

/* ---- anual: el cronograma ----
   Renglones de alto fijo y nombres en una línea: el corte de hojas en
   DocumentView cuenta renglones, no estima cuántos nombres envuelven. */
.crono{font-size:.88rem}
.cr-fila{display:flex; align-items:center; height:21px; border-bottom:1px solid var(--borde)}
.cr-nom{flex:none; width:60mm; padding-right:8px; white-space:nowrap; overflow:hidden;
  text-overflow:ellipsis; color:var(--texto)}
.cr-pista{flex:1; align-self:stretch; position:relative; display:flex;
  background:repeating-linear-gradient(90deg, transparent 0 calc(100%/12 - 1px), var(--borde) calc(100%/12 - 1px) calc(100%/12))}
.cr-pista i{flex:1; font-style:normal; text-align:center; font-variant-numeric:tabular-nums}
.cr-barra{position:absolute; top:6px; bottom:6px; border-radius:2px; background:var(--magenta)}
.cr-barra.ancla{background:var(--rosa)}
.cr-h{flex:none; width:11mm; text-align:right; color:var(--gris); font-variant-numeric:tabular-nums}
.cr-meses{height:24px; border-bottom:1.5px solid var(--vino)}
.cr-meses .cr-pista{background:none}
.cr-meses i{font-family:var(--cond); font-weight:700; font-size:.82rem; letter-spacing:.06em;
  text-transform:uppercase; color:var(--gris); align-self:center}
.cr-meses .cr-h{font-family:var(--cond); font-weight:700; color:var(--gris)}
.cr-grupo{display:flex; align-items:baseline; gap:10px; height:27px; padding-top:8px;
  border-bottom:1px solid var(--borde-2)}
.cr-grupo h5{margin:0; font-family:var(--cond); font-weight:700; font-size:1.1rem; color:var(--magenta)}
.cr-grupo span{font-size:.8rem; color:var(--gris)}
.cr-total{height:24px; border-top:1.5px solid var(--vino); border-bottom:0; background:var(--tinte)}
.cr-total .cr-nom{font-family:var(--cond); font-weight:700; letter-spacing:.08em;
  text-transform:uppercase; font-size:.8rem; color:var(--gris); padding-left:6px}
.cr-total .cr-pista{background:none}
.cr-total i{font-family:var(--cond); font-weight:700; color:var(--vino); align-self:center}
.cr-total .cr-h{font-family:var(--cond); font-weight:700; color:var(--vino); padding-right:4px}
.cr-nota{margin:5mm 0 0; font-size:.9rem; color:var(--gris)}

/* ---- anual: el inventario marca lo que ya está en el plan ---- */
.inv-grupo li .p{color:var(--magenta)}
.inv-grupo li.plan .n{font-weight:600; color:var(--vino)}

/* ---- anual: cómo aprovecharla ---- */
.pasos{list-style:none; margin:10px 0 6mm; padding:0; display:grid; gap:8px}
.pasos li{display:flex; gap:14px; align-items:baseline; padding-bottom:8px;
  border-bottom:1px solid var(--borde)}
.pasos b{flex:none; font-family:var(--cond); font-weight:700; font-size:1.9rem; line-height:1;
  color:var(--magenta); width:22px}
.pasos span{font-size:1.05rem; line-height:1.5}
.contacto{border:1px solid var(--borde-2); border-radius:12px; padding:12px 16px; margin-bottom:6mm;
  background:var(--crema)}
.contacto .k{display:block; font-family:var(--cond); font-weight:700; font-size:.86rem;
  letter-spacing:.18em; text-transform:uppercase; color:var(--magenta); margin-bottom:3px}
.contacto p{margin:0; font-size:1rem; line-height:1.55}
.contacto .tel{margin-top:4px; font-weight:600; color:var(--vino)}
.hoja.anual .firma .avatar{background:var(--magenta); box-shadow:none}

@media screen and (max-width:820px){
  .logos{grid-template-columns:repeat(3,1fr)}
  .fotos{grid-template-columns:1fr 1fr}
  .fotos img{height:130px}
  .fotos img:first-child{grid-column:1 / -1; height:180px}
  .resenas{grid-template-columns:1fr}
  .pa-banda{padding:18px 20px}
  .pa-cuerpo{padding:26px 20px 30px}
  .pa-nombre{font-size:2.1rem}
  .pa-ficha{grid-template-columns:1fr; margin-top:22px}
  .pa-ficha div:nth-child(odd){border-right:0}
  .pa-ficha div:nth-last-child(2){border-bottom:1px solid var(--borde-2)}
  .kpis{grid-template-columns:1fr 1fr}
  .kpis div:nth-child(2){border-right:0}
  .kpis div:nth-child(-n+2){border-bottom:1px solid var(--borde-2)}
  .crono{overflow-x:auto}
  .cr-fila,.cr-grupo{min-width:620px}
  .cr-nom{width:170px}
}
/* ====================== portada del catálogo ====================== */
/* El hero de academy.dpsys.com.mx: vino con resplandor rosa, título en Anton
   en mayúsculas con la segunda línea en rosa, y cifras grandes en Antonio.
   Solo lo necesario: especialidades, cursos, horas y lo que da la membresía. */
.portada .ct-inner{padding:15mm 17mm 12mm}
.ct-top{display:flex; align-items:center; justify-content:space-between}
.ct-top img{height:38px; width:auto; display:block}
.ct-top span{font-family:var(--cond); font-weight:700; font-size:.95rem; letter-spacing:.24em;
             text-transform:uppercase; color:rgba(255,255,255,.62)}
.ct-eyebrow{display:block; margin-top:17mm; font-family:var(--cond); font-weight:700; font-size:1.05rem;
            letter-spacing:.2em; text-transform:uppercase; color:var(--rosa)}
.ct-h1{margin:8px 0 0; font-family:var(--display); font-weight:400; font-size:6.4rem; line-height:.94;
       text-transform:uppercase; letter-spacing:.005em}
.ct-h1 span{color:var(--rosa); display:block}
.ct-lead{margin:7mm 0 0; max-width:31em; font-size:1.2rem; line-height:1.55; color:rgba(255,255,255,.8)}

.ct-cifras{display:grid; grid-template-columns:repeat(3,1fr); margin-top:11mm;
           border-top:1px solid rgba(255,255,255,.16); border-bottom:1px solid rgba(255,255,255,.16)}
.ct-cifras div{padding:5mm 0 5mm 6mm; border-left:1px solid rgba(255,255,255,.16)}
.ct-cifras div:first-child{padding-left:0; border-left:0}
.ct-cifras b{display:block; font-family:var(--display); font-weight:400; font-size:3.9rem; line-height:1}
.ct-cifras b i{font-style:normal; font-size:.55em; color:var(--rosa); margin-left:3px}
.ct-cifras span{display:block; margin-top:6px; font-family:var(--cond); font-weight:700; font-size:.95rem;
                letter-spacing:.18em; text-transform:uppercase; color:rgba(255,255,255,.72)}
.ct-cifras small{display:block; margin-top:2px; font-size:.82rem; color:rgba(255,255,255,.5)}

.ct-k{display:block; margin:9mm 0 3.5mm; font-family:var(--cond); font-weight:700; font-size:.9rem;
      letter-spacing:.2em; text-transform:uppercase; color:rgba(255,255,255,.55)}
.ct-esp{display:grid; grid-template-columns:repeat(2,1fr); gap:0 12mm; margin:0; padding:0; list-style:none}
.ct-esp li{display:flex; align-items:baseline; gap:6px; padding:6px 0;
           border-bottom:1px solid rgba(255,255,255,.1)}
.ct-esp li::before{content:''; flex:none; width:5px; height:5px; border-radius:999px;
                   background:var(--rosa); transform:translateY(-2px)}
.ct-esp .n{flex:1; min-width:0; font-family:var(--cond); font-weight:700; font-size:1.12rem;
           letter-spacing:.02em}
.ct-esp .d{flex:none; font-size:.8rem; color:rgba(255,255,255,.55); font-variant-numeric:tabular-nums}

.ct-membresia{display:grid; grid-template-columns:repeat(4,1fr); gap:3mm; margin-top:auto; padding-top:9mm}
.ct-membresia div{padding:4mm 4mm 4.2mm; border-radius:10px; background:rgba(255,255,255,.06);
                  border:1px solid rgba(255,255,255,.12)}
.ct-membresia b{display:block; font-family:var(--cond); font-weight:700; font-size:1.2rem; line-height:1.15}
.ct-membresia span{display:block; margin-top:4px; font-size:.82rem; line-height:1.4; color:rgba(255,255,255,.66)}
.ct-membresia div:first-child{background:var(--rosa); border-color:var(--rosa)}
.ct-membresia div:first-child span{color:rgba(255,255,255,.88)}

.ct-pie{display:flex; gap:16px; margin-top:7mm; font-size:.86rem; color:rgba(255,255,255,.55)}
.ct-pie .folio{margin-left:auto; font-family:var(--cond); font-weight:700; font-size:1rem; color:#fff}
.ct-pie .folio i{font-style:normal; font-weight:600; font-size:.82em; color:rgba(255,255,255,.5)}
</style>

{{-- ========================================================================
     Ruta final · plantilla Carta de cinco hojas
     Esta capa queda aislada del documento enfocado y del catálogo: comparten
     fuentes y tokens, pero la ruta final tiene su propio ritmo editorial.
     ======================================================================== --}}
<style>
.doc:has(.ruta-final-doc){padding:0}

.ruta-final-doc{
  --rf-magenta:#800E64; --rf-uva:#4B0055; --rf-tinta:#1D1522; --rf-texto:#4A4150; --rf-gris:#6E6573;
  --rf-gris-2:#9A8FA0; --rf-borde:#E5E2E8; --rf-borde-2:#D6D2DA; --rf-fondo:#F4F3F6;
  --rf-pill:#EFEDF1; --rf-papel:#FFFFFF; --rf-display:'Anton','Arial Narrow',Impact,sans-serif;
  --rf-cond:'Antonio','Arial Narrow',sans-serif; --rf-body:'Poppins','Segoe UI',Arial,sans-serif;
  font-family:var(--rf-body); color:var(--rf-tinta); font-size:13px; line-height:1.45;
  -webkit-print-color-adjust:exact; print-color-adjust:exact;
}
.ruta-final-page{
  width:215.9mm !important; height:279.4mm; min-height:0 !important; margin:0 !important; padding:0;
  position:relative; overflow:hidden; page-break-after:always; break-after:page;
  display:flex; flex-direction:column; background:var(--rf-papel); box-shadow:none !important;
}
.ruta-final-page:last-child{page-break-after:auto; break-after:auto}
.ruta-final-page.rf-interior{padding:11mm 13mm 8mm}

/* ---------- encabezado y pie ---------- */
.ruta-final-page .enc{
  display:flex; justify-content:space-between; align-items:center; gap:0; flex:none;
  font-family:var(--rf-cond); font-weight:700; letter-spacing:.14em; text-transform:uppercase;
  font-size:12px; color:var(--rf-uva); border-bottom:1px solid var(--rf-borde);
  padding:0 0 9px; margin:0 0 16px;
}
.ruta-final-page .enc span:last-child{color:var(--rf-gris)}
.ruta-final-page .pie{
  margin-top:auto; padding:8px 0 0; border-top:1px solid var(--rf-borde);
  display:flex; justify-content:space-between; align-items:baseline; gap:16px;
  font-size:11px; color:var(--rf-gris-2); letter-spacing:0;
}

/* ---------- logo y portada ---------- */
.rf-logo-sys .rf-logo-hx{fill:var(--rf-magenta); stroke:var(--rf-magenta); stroke-width:9; stroke-linejoin:round}
.rf-logo-sys .rf-logo-in{fill:none; stroke:#fff; stroke-width:2.4}
.rf-logo-sys text{font-family:'Caveat',cursive; font-weight:500; fill:#fff}
.rf-portada .rf-barra{height:8px; flex:none; background:linear-gradient(90deg,var(--rf-uva),var(--rf-magenta))}
.rf-portada-cuerpo{flex:1; display:flex; flex-direction:column; padding:14mm 17mm 11mm}
.rf-top{display:flex; justify-content:space-between; align-items:center}
.rf-marca{display:flex; align-items:center; gap:12px}
.rf-marca span{font-family:var(--rf-cond); font-weight:700; font-size:16px; letter-spacing:.06em; line-height:1.1;
  color:var(--rf-uva); text-transform:uppercase}
.rf-activa{font-family:var(--rf-cond); font-weight:700; font-size:12px; letter-spacing:.14em; text-transform:uppercase;
  border:1.5px solid var(--rf-uva); color:var(--rf-uva); border-radius:20px; padding:4px 13px}
.rf-para{margin-top:auto; font-family:var(--rf-cond); font-weight:600; font-size:13px; letter-spacing:.14em;
  text-transform:uppercase; color:var(--rf-gris)}
.rf-nom{font-family:var(--rf-cond); font-weight:700; font-size:30px; line-height:1.15}
.rf-puesto{font-size:15px; color:var(--rf-gris)}
.rf-portada h1{font-family:var(--rf-display); font-weight:400; font-size:84px; line-height:.95; text-transform:uppercase;
  color:var(--rf-uva); margin:26px 0 14px}
.rf-bajada{font-size:16px; color:var(--rf-texto); max-width:88%; margin:0}
.rf-ficha{margin-top:28px; border:1.5px solid var(--rf-borde); border-radius:10px; display:grid; grid-template-columns:1fr 1fr}
.rf-ficha div{padding:14px 18px; border-bottom:1px solid var(--rf-borde)}
.rf-ficha div:nth-child(odd){border-right:1px solid var(--rf-borde)}
.rf-ficha div:nth-last-child(-n+2){border-bottom:0}
.rf-ficha span{display:block; font-size:11px; letter-spacing:.12em; text-transform:uppercase; color:var(--rf-gris)}
.rf-ficha b{font-family:var(--rf-cond); font-weight:700; font-size:22px; color:var(--rf-uva)}
.rf-arranque{margin-top:16px; background:var(--rf-fondo); border-radius:10px; padding:14px 18px; display:flex; align-items:center; gap:16px}
.rf-arranque i{width:38px; height:43px; flex:none; background:var(--rf-magenta); clip-path:polygon(50% 0,100% 25%,100% 75%,50% 100%,0 75%,0 25%);
  display:grid; place-items:center; font-style:normal; font-family:var(--rf-display); font-size:19px; color:#fff}
.rf-arranque span{display:block; font-family:var(--rf-cond); font-weight:600; font-size:12px; letter-spacing:.14em;
  text-transform:uppercase; color:var(--rf-gris)}
.rf-arranque b{font-family:var(--rf-cond); font-weight:700; font-size:22px; color:var(--rf-uva)}
.rf-firma-portada{margin-top:20px; font-size:12px; color:var(--rf-gris); display:flex; justify-content:space-between}

/* ---------- ruta mes a mes ---------- */
.rf-eyebrow{font-family:var(--rf-cond); font-weight:700; letter-spacing:.14em; text-transform:uppercase;
  font-size:12.5px; color:var(--rf-gris)}
.rf-h2{font-family:var(--rf-display); font-weight:400; font-size:40px; line-height:1; color:var(--rf-uva);
  text-transform:uppercase; margin:4px 0 8px}
.rf-objetivo{font-size:13px; color:var(--rf-texto); margin:0 0 10px}
.rf-leer{display:flex; gap:12px; background:var(--rf-fondo); border-radius:9px; padding:7px 16px; margin-bottom:12px; font-size:12.5px}
.rf-leer span{display:flex; gap:8px; align-items:center; flex:1; line-height:1.25}
.rf-leer b{font-family:var(--rf-display); font-weight:400; color:var(--rf-uva); font-size:20px; line-height:1}
.rf-etapa{display:flex; align-items:baseline; gap:14px; padding:2px 0 3px; border-bottom:2px solid var(--rf-uva)}
.rf-etapa .n{font-family:var(--rf-cond); font-weight:700; font-size:13px; letter-spacing:.16em; text-transform:uppercase; color:var(--rf-magenta)}
.rf-etapa b{font-family:var(--rf-display); font-weight:400; font-size:21px; letter-spacing:.03em; text-transform:uppercase; color:var(--rf-uva)}
.rf-etapa .m{margin-left:auto; font-family:var(--rf-cond); font-weight:600; font-size:13.5px; letter-spacing:.1em; text-transform:uppercase; color:var(--rf-gris)}
.rf-mapa-pagina > *{flex-shrink:0}
.rf-mapa-pagina .rf-etapa .n,.rf-mapa-pagina .rf-etapa .m{flex-shrink:0}
.rf-mapa-pagina .rf-etapa b{min-width:0}
.rf-camino{padding:7px 0 5px}
.rf-mes{display:grid; grid-template-columns:36px 1fr; gap:14px; position:relative; padding-bottom:5px}
.rf-mes::before{content:""; position:absolute; left:17px; top:4px; bottom:-4px; width:3px; background:var(--rf-borde)}
.rf-mes.ultimo::before{display:none}
.rf-nodo{position:relative; isolation:isolate; z-index:1; width:36px; height:40px; background:var(--rf-magenta);
  clip-path:polygon(50% 0,100% 25%,100% 75%,50% 100%,0 75%,0 25%); display:grid; place-items:center;
  font-family:var(--rf-display); font-size:17px; color:var(--rf-magenta)}
.rf-nodo::after{content:""; position:absolute; inset:3px; background:#fff;
  clip-path:polygon(50% 0,100% 25%,100% 75%,50% 100%,0 75%,0 25%); z-index:-1}
.rf-mes.inicio .rf-nodo{color:#fff}
.rf-mes.inicio .rf-nodo::after{display:none}
.rf-mes.libre .rf-nodo{background:#CFCAD3; color:var(--rf-gris-2)}
.rf-mes-cab{display:flex; align-items:center; gap:12px; margin:4px 0 2px}
.rf-mes-cab b{font-family:var(--rf-cond); font-weight:700; font-size:17px; letter-spacing:.06em; text-transform:uppercase; color:var(--rf-uva)}
.rf-mes-cab .cal{font-size:13px; color:var(--rf-gris)}
.rf-mes-cab .carga{margin-left:auto; font-size:12px; color:var(--rf-gris); font-variant-numeric:tabular-nums}
.rf-tag-aqui{font-family:var(--rf-cond); font-weight:700; font-size:11.5px; letter-spacing:.12em; text-transform:uppercase;
  background:var(--rf-magenta); color:#fff; border-radius:5px; padding:2px 10px}
.rf-curso{display:flex; align-items:center; gap:14px; background:#fff; border:1.5px solid var(--rf-borde); border-radius:8px; padding:3px 12px; margin-top:3px}
.rf-mes.inicio .rf-curso{border-color:#B9A3BF}
.rf-curso .t{flex:1; min-width:0}
.rf-curso .t b{display:block; font-weight:600; font-size:14.5px; line-height:1.25}
.rf-curso .t span{display:block; font-size:11.5px; color:var(--rf-gris); line-height:1.25}
.rf-curso .h{font-family:var(--rf-cond); font-weight:700; font-size:13.5px; color:var(--rf-uva); background:var(--rf-pill);
  border-radius:16px; padding:2px 12px; min-width:60px; text-align:center; white-space:nowrap; font-variant-numeric:tabular-nums}
.rf-curso.sigue{border-style:dashed; border-color:var(--rf-borde-2); background:#FAFAFB}
.rf-curso.sigue .t b{color:#5E5364}
.rf-curso.sigue .h{background:transparent; color:var(--rf-gris-2); font-weight:600}
.rf-libre-txt{font-size:12.5px; color:#5E5364; background:#FAFAFB; border:1.5px dashed var(--rf-borde-2);
  border-radius:8px; padding:9px 13px; margin-top:4px}
.rf-meta{display:flex; gap:16px; align-items:center; background:var(--rf-fondo); border-radius:12px; padding:12px 18px; margin-top:6px}
.rf-meta i{width:48px; height:54px; flex:none; background:var(--rf-magenta); color:#fff; clip-path:polygon(50% 0,100% 25%,100% 75%,50% 100%,0 75%,0 25%);
  display:grid; place-items:center; font-style:normal; font-family:var(--rf-cond); font-weight:700; font-size:11px; letter-spacing:.1em}
.rf-meta b{display:block; font-family:var(--rf-display); font-weight:400; font-size:19px; letter-spacing:.03em; text-transform:uppercase; color:var(--rf-uva)}
.rf-meta span{font-size:13px; color:var(--rf-texto)}
.rf-flex-nota{font-size:12px; color:var(--rf-gris); margin-top:10px}

/* ---------- inventario ---------- */
.rf-intro{font-size:14px; color:var(--rf-texto); margin:0 0 16px; max-width:80%}
.rf-inv{columns:2; column-gap:30px}
.rf-inv-grupo{break-inside:avoid; margin-bottom:18px}
.rf-inv h3{font-family:var(--rf-cond); font-weight:700; font-size:15px; letter-spacing:.06em; text-transform:uppercase;
  color:var(--rf-uva); border-bottom:1.5px solid var(--rf-uva); padding-bottom:4px; margin:0 0 4px}
.rf-inv ul{margin:0; padding:0}
.rf-inv li{list-style:none; display:flex; align-items:center; gap:10px; padding:5px 0; border-bottom:1px solid var(--rf-borde); font-size:13px}
.rf-inv-dot{width:10px; height:11px; flex:none; clip-path:polygon(50% 0,100% 25%,100% 75%,50% 100%,0 75%,0 25%); background:var(--rf-borde-2)}
.rf-inv-dot.planned{background:var(--rf-magenta)}
.rf-inv li.plan{font-weight:600}
.rf-inv li.sub{color:var(--rf-gris); padding-left:20px}
.rf-inv li .rf-inv-arrow{width:10px; flex:none; color:var(--rf-gris)}
.rf-inv-name{flex:1; min-width:0}
.rf-inv li .x{margin-left:auto; color:var(--rf-gris); font-variant-numeric:tabular-nums; font-weight:400; white-space:nowrap}
.rf-leyenda{display:flex; gap:22px; font-size:12px; color:var(--rf-gris); margin-top:6px}
.rf-leyenda span{display:flex; align-items:center; gap:7px; white-space:nowrap}
.rf-leyenda i{width:10px; height:11px; clip-path:polygon(50% 0,100% 25%,100% 75%,50% 100%,0 75%,0 25%); background:var(--rf-borde-2)}
.rf-leyenda i.p{background:var(--rf-magenta)}

/* ---------- cierre ---------- */
.rf-pasos{margin:10px 0 26px}
.rf-pasos div{display:flex; gap:18px; align-items:flex-start; border-bottom:1px solid var(--rf-borde); padding:14px 0; font-size:15px}
.rf-pasos b{font-family:var(--rf-display); font-weight:400; font-size:30px; line-height:1; color:var(--rf-uva); width:24px}
.rf-pasos em{color:var(--rf-gris); font-style:italic}
.rf-dc3{display:flex; gap:18px; align-items:center; background:var(--rf-fondo); border-radius:12px; padding:18px 22px; margin-bottom:16px}
.rf-dc3 .sello{width:60px; height:66px; flex:none; background:var(--rf-magenta); clip-path:polygon(50% 0,100% 25%,100% 75%,50% 100%,0 75%,0 25%);
  display:grid; place-items:center; font-family:var(--rf-cond); font-weight:700; font-size:14px; color:#fff; letter-spacing:.06em}
.rf-dc3 p{font-size:14px; color:var(--rf-texto); margin:0}
.rf-asesor{border:1.5px solid var(--rf-borde); border-radius:12px; padding:18px 22px; display:grid; gap:6px}
.rf-asesor h4{font-family:var(--rf-cond); font-weight:700; font-size:14px; letter-spacing:.12em; text-transform:uppercase; color:var(--rf-uva); margin:0}
.rf-asesor p{font-size:14px; margin:0}
.rf-asesor .tel{color:var(--rf-gris); font-size:13.5px}
.rf-firma-asesor{display:flex; gap:14px; align-items:center; margin-top:24px}
.rf-firma-asesor i{width:44px; height:49px; background:var(--rf-uva); clip-path:polygon(50% 0,100% 25%,100% 75%,50% 100%,0 75%,0 25%);
  display:grid; place-items:center; color:#fff; font-style:normal; font-family:var(--rf-cond); font-weight:700; font-size:15px}
.rf-firma-asesor b{display:block; font-size:15px}
.rf-firma-asesor span{font-size:13px; color:var(--rf-gris)}

@media print{
  .doc:has(.ruta-final-doc){padding:0}
  .ruta-final-page{margin:0 !important; box-shadow:none !important}
}
@media screen and (max-width:820px){
  .doc:has(.ruta-final-doc){padding:0}
  .ruta-final-page{width:auto !important; height:auto; min-height:0 !important; margin:0 !important; padding:26px 20px 30px; box-shadow:none !important; border-bottom:1px solid var(--rf-borde)}
  .ruta-final-page.rf-portada{padding:0}
  .rf-portada-cuerpo{padding:26px 20px 30px}
  .rf-portada h1{font-size:2.9rem}
  .rf-nom{font-size:1.7rem}
  .rf-ficha{grid-template-columns:1fr; margin-top:22px}
  .rf-ficha div:nth-child(odd){border-right:0}
  .rf-ficha div:nth-last-child(2){border-bottom:1px solid var(--rf-borde)}
  .rf-leer{flex-direction:column; gap:7px}
  .rf-inv{columns:1}
  .rf-leyenda{flex-wrap:wrap; gap:8px 16px}
}
</style>
