<style>
/* PDF inicial: Carta exacta y espaciado flexible del itinerario. */
.ruta-inicial-doc .hoja{width:215.9mm; min-height:279.3mm}
.ruta-inicial-doc .inicial-ruta > .cuerpo{display:flex; flex-direction:column}
.ruta-inicial-doc .ruta-lista{display:flex; flex-direction:column; gap:12px; margin-top:8px}
.ruta-inicial-doc .ruta-grupo{margin:12px 0 0; break-after:avoid}
.ruta-inicial-doc .ruta-grupo h4{font-family:var(--cond); font-weight:700; font-size:1.05rem;
  text-transform:uppercase; letter-spacing:.1em; color:var(--magenta); margin:0}
.ruta-inicial-doc .refuerzo-contexto{font-size:.95rem; line-height:1.5; color:var(--gris); margin:6px 0 0;
  font-weight:400; text-transform:none; letter-spacing:normal; overflow-wrap:anywhere}
.ruta-inicial-doc .ruta-lista .bloque{margin-bottom:2px}
.ruta-inicial-doc .curso{padding:6px 0; min-height:39px}
.ruta-inicial-doc .curso-refuerzo{align-items:flex-start; margin:8px 0; padding:12px;
  border:1px solid var(--borde); border-radius:10px; background:var(--crema); break-inside:avoid}
.ruta-inicial-doc .curso-refuerzo .cu-n{background:var(--crema)}
.ruta-inicial-doc .curso-refuerzo .cu-meta{align-self:flex-start}
.ruta-inicial-doc .cu-motivo{margin-top:8px}
.ruta-inicial-doc .cu-motivo > span{display:block; font-family:var(--cond); font-size:.82rem;
  font-weight:700; letter-spacing:.09em; text-transform:uppercase; color:var(--magenta)}
.ruta-inicial-doc .cu-motivo p{font-size:.98rem; line-height:1.55; color:var(--texto); margin:4px 0 0;
  overflow-wrap:anywhere}
.ruta-inicial-doc .alcance{margin-top:20px}
.ruta-inicial-doc .firma-logo{width:44px; height:44px; object-fit:contain}
.ruta-inicial-doc .inicial-cierre > .cuerpo{display:flex; flex-direction:column; justify-content:space-between; gap:12px}
.ruta-inicial-doc .inicial-cierre .cta{margin-top:12px}
@media print{
  .ruta-inicial-doc .hoja{margin:0; break-inside:avoid; page-break-inside:avoid}
  .ruta-inicial-doc .curso-refuerzo{padding:8px 10px; margin:6px 0}
  .ruta-inicial-doc .cu-motivo{margin-top:4px}
  .ruta-inicial-doc .cu-motivo p{font-size:.94rem; margin-top:3px}
  .ruta-inicial-doc .inicial-ruta .enc{margin-bottom:5mm}
  .ruta-inicial-doc .inicial-ruta .objetivo{margin-bottom:8px}
  .ruta-inicial-doc .ruta-lista{gap:10px; margin-top:4px}
  .ruta-inicial-doc .ruta-grupo{margin-top:10px}
  .ruta-inicial-doc .solo-refuerzos .curso-refuerzo{min-height:120px; padding:16px; margin:10px 0}
  .ruta-inicial-doc .solo-refuerzos .cu-nom{font-size:1.3rem}
  .ruta-inicial-doc .solo-refuerzos .cu-motivo{margin-top:10px}
  .ruta-inicial-doc .solo-refuerzos .cu-motivo p{font-size:1.1rem; line-height:1.6}
  .ruta-inicial-doc .refuerzo-apoyo{margin-top:16px; break-inside:avoid}
  .ruta-inicial-doc .refuerzo-apoyo .tarjeta{margin-top:12px; margin-bottom:0}
}
@media screen and (max-width:820px){
  .ruta-inicial-doc .hoja{width:auto; min-height:0}
  .ruta-inicial-doc .ruta-lista{gap:16px}
  .ruta-inicial-doc .curso{display:grid; grid-template-columns:24px minmax(0,1fr) auto; gap:8px;
    align-items:start; min-width:0}
  .ruta-inicial-doc .cu-nom{overflow-wrap:anywhere}
  .ruta-inicial-doc .cu-meta{width:auto; padding:0; margin:0; align-self:start}
  .ruta-inicial-doc .cu-motivo p{font-size:.94rem}
  .ruta-inicial-doc .bl-enc{display:grid; grid-template-columns:5px minmax(0,1fr); gap:6px 10px}
  .ruta-inicial-doc .bl-tit{min-width:0}
  .ruta-inicial-doc .bl-tit h3{overflow-wrap:anywhere}
  .ruta-inicial-doc .bl-enc > .dur{grid-column:2; justify-self:start}
  .ruta-inicial-doc .enc{display:grid; grid-template-columns:22px minmax(0,1fr); gap:4px 8px}
  .ruta-inicial-doc .enc .marca{font-size:.9rem; letter-spacing:.06em; overflow-wrap:anywhere}
  .ruta-inicial-doc .enc .der{grid-column:2; margin:0; font-size:.8rem}
  .ruta-inicial-doc .pt-eyebrow{font-size:.78rem; letter-spacing:.1em; max-width:100%}
  .ruta-inicial-doc .pt-h1{font-size:clamp(2rem,8vw,3.6rem); overflow-wrap:anywhere}
  .ruta-inicial-doc .pt-nombre{overflow-wrap:anywhere}
  .ruta-inicial-doc .pt-pie{flex-wrap:wrap; gap:8px 12px}
  .ruta-inicial-doc .pt-pie .der{flex-basis:100%; text-align:left; margin:0}
  .ruta-inicial-doc .alcance .cab{flex-wrap:wrap; gap:14px}
  .ruta-inicial-doc .alcance .c{width:100%; justify-content:space-between}
  .ruta-inicial-doc .alcance .cab .c{gap:10px}
  .ruta-inicial-doc .cta-botones{flex-direction:column; align-items:stretch}
  .ruta-inicial-doc .cta-botones a{text-align:center; white-space:normal}
}
</style>
<style>
/* La trayectoria institucional ocupa una sola hoja Carta. */
.ruta-inicial-doc .confianza-sys .enc{margin-bottom:20px}
.ruta-inicial-doc .confianza-sys .cuerpo{display:flex; flex-direction:column; gap:14px}
.confianza-sys .h-sec h2{font-size:2.5rem}
.confianza-sys .h-sec .kicker{font-size:.85rem}
.confianza-sys .conf-intro{margin:0; font-size:1rem; line-height:1.5; color:var(--gris)}
.confianza-sys h4{font-family:var(--cond); font-weight:700; font-size:1.05rem; line-height:1.2;
  text-transform:uppercase; letter-spacing:.08em; color:var(--ink); margin:0 0 8px}
.conf-cifras{display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); background:var(--vino);
  border-radius:12px; color:white; padding:13px 16px; gap:14px}
.conf-cifras > div + div{border-left:1px solid #ffffff30; padding-left:16px}
.conf-cifras b{font-family:var(--display); font-size:2.45rem; line-height:1; display:block; font-weight:400}
.conf-cifras span{font-family:var(--cond); font-size:.85rem; line-height:1.3; display:block; margin-top:5px; color:#f2d5e6}
.confianza-sys .logos{grid-template-columns:repeat(6,minmax(0,1fr)); margin:0; gap:12px}
.confianza-sys .logos span{height:42px}
.confianza-sys .logos img{max-height:37px}
.conf-avales{display:grid; grid-template-columns:repeat(6,minmax(0,1fr)); gap:7px}
.conf-avales a{display:flex; flex-direction:column; align-items:center; justify-content:center; gap:7px;
  border:1px solid var(--borde); border-radius:9px; padding:12px 7px; text-decoration:none; color:var(--ink); min-width:0}
.conf-avales img{width:100%; height:44px; object-fit:contain}
.conf-avales span{font-family:var(--cond); font-size:.86rem; text-align:center; line-height:1.2}
.conf-avales small{color:var(--magenta); font-size:.72rem; font-weight:500}
.conf-alcance{margin:6px 0 0; font-size:.78rem; color:var(--gris); line-height:1.4}
.conf-fundadores-grid{display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px}
.conf-fundador{display:flex; align-items:stretch; gap:14px; min-width:0; padding:13px;
  background:var(--crema); border:1px solid var(--borde); border-radius:9px}
.conf-fundador>img{width:96px; height:126px; object-fit:cover; object-position:center 24%;
  flex:0 0 auto; border-radius:6px}
.conf-fundador-info{display:flex; flex-direction:column; min-width:0; gap:4px}
.conf-fundador h5{margin:0; font-family:var(--cond); font-size:.98rem; line-height:1.2; color:var(--ink)}
.conf-fundador p{margin:0; font-size:.82rem; line-height:1.45; color:var(--gris)}
.conf-fundador-links{display:flex; flex-wrap:wrap; gap:4px 12px; margin-top:auto}
.conf-fundador-links a{font-size:.7rem; font-weight:600; color:var(--magenta); text-decoration:none}
.conf-verifica{display:flex; flex-wrap:wrap; align-items:center; justify-content:center; gap:6px 16px; padding:8px 12px;
  background:var(--crema); border-left:3px solid var(--rosa); border-radius:7px}
.conf-verifica a{font-size:.82rem; font-weight:600; color:var(--magenta); text-decoration:none}
.conf-redes{display:grid; grid-template-columns:repeat(5,minmax(0,1fr)); gap:7px}
.conf-redes a{display:flex; align-items:center; justify-content:center; gap:6px; padding:6px 3px; border:1px solid var(--borde-2);
  border-radius:6px; color:var(--magenta); font-size:.78rem; font-weight:500; text-decoration:none}
.conf-redes svg{width:17px; height:17px; fill:currentColor; stroke:currentColor; stroke-width:2; flex:0 0 auto}
.conf-redes svg rect,.conf-redes svg circle{fill:none}
.conf-redes svg .punto{fill:currentColor; stroke:none}
.conf-fuentes{margin:0; font-size:.72rem; line-height:1.4; color:var(--gris)}
.conf-fuentes a{color:inherit; text-decoration:none}
@media screen and (max-width:820px){
  .ruta-inicial-doc .confianza-sys .cuerpo{gap:20px}
  .confianza-sys .h-sec h2{font-size:2rem}
  .conf-cifras{padding:14px 12px; gap:9px}
  .conf-cifras > div + div{padding-left:9px}
  .conf-cifras b{font-size:1.8rem}
  .conf-cifras span{font-size:.75rem}
  .conf-avales{grid-template-columns:repeat(3,minmax(0,1fr)); gap:9px}
  .conf-avales img{height:38px}
  .confianza-sys .logos{grid-template-columns:repeat(3,minmax(0,1fr))}
  .conf-fundadores-grid{grid-template-columns:1fr}
  .conf-fundador>img{width:96px; height:126px}
  .conf-redes{grid-template-columns:repeat(3,minmax(0,1fr))}
  .conf-verifica a{font-size:.9rem; overflow-wrap:anywhere}
}
</style>
