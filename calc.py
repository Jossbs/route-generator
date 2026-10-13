# -*- coding: utf-8 -*-
# Calculo reproducible de nivel_complejidad - Catalogo DP SyS
# C1 carga formativa (horas) | C2 profundidad por leccion (h/lec)
# C3 prerrequisito tecnico   | C4 alcance de aplicacion
import json, math
from pathlib import Path

def c1(h):
    return 1 if h<=8 else 2 if h<=16 else 3 if h<=24 else 4
def c2(h,l):
    d=h/l
    return 1 if d<1.2 else 2 if d<1.8 else 3 if d<=2.4 else 4

# (slug, nombre, especialidad, lecciones, horas, C3, C4)
C = [
("lean-manufacturing","Lean Manufacturing","Lean Manufacturing",12,40,2,4),
("lean-thinking","Lean Thinking","Lean Manufacturing",8,16,1,3),
("toyota-production-system","Toyota Production System","Lean Manufacturing",8,16,2,4),
("tpm-mantenimiento-total-productivo","TPM - Mantenimiento Total Productivo","Lean Manufacturing",8,16,2,3),
("value-stream-mapping","Value Stream Mapping","Lean Manufacturing",6,12,2,4),
("smed","SMED","Lean Manufacturing",6,12,2,2),
("metodologia-kanban","Metodologia Kanban","Lean Manufacturing",5,10,2,3),
("los-8-desperdicios","Los 8 Desperdicios","Lean Manufacturing",5,8,1,2),
("trabajo-estandar","Trabajo Estandar","Lean Manufacturing",4,8,2,2),
("tiempo-de-takt-vs-tiempo-de-ciclo","Balanceo de Lineas","Lean Manufacturing",4,6,2,3),
("historia-de-la-economia-y-manufactura","Historia de la Economia y Manufactura","Lean Manufacturing",4,6,1,1),
("white-belt-six-sigma","White Belt - Six Sigma","Six Sigma",5,8,1,1),
("yellow-belt-six-sigma","Yellow Belt - Six Sigma","Six Sigma",10,24,3,2),
("green-belt-six-sigma","Green Belt - Six Sigma","Six Sigma",14,40,4,3),
("black-belt-six-sigma","Black Belt - Six Sigma","Six Sigma",10,40,4,4),
("dmaic","DMAIC","Six Sigma",8,16,2,3),
("liderazgo-para-ingenieros","Liderazgo para Ingenieros","Soft Skills",9,16,1,3),
("experto-en-ventas","Experto en Ventas","Soft Skills",8,16,1,2),
("liderazgo-para-lideres-y-supervisores","Liderazgo para Lideres y Supervisores","Soft Skills",7,12,1,3),
("comunicacion-efectiva","Comunicacion Efectiva","Soft Skills",7,12,1,2),
("manejo-del-personal","Manejo del Personal","Soft Skills",6,10,1,3),
("inteligencia-emocional","Inteligencia Emocional","Soft Skills",6,10,1,1),
("integracion-y-trabajo-en-equipo","Integracion y Trabajo en Equipo","Soft Skills",5,8,1,2),
("el-arte-de-la-persuasion","El Arte de la Persuasion","Soft Skills",5,8,1,1),
("nom-035","NOM-035","Soft Skills",5,8,2,4),
("PENDIENTE-pqt-contraten","PQT Contraten","Soft Skills",12,9,1,1),
("PENDIENTE-como-venderte-a-las-empresas","Como venderte a las empresas","Soft Skills",5,3,1,1),
("PENDIENTE-curriculum-vitae-de-alto-impacto","Curriculum Vitae de Alto Impacto","Soft Skills",5,3,1,1),
("PENDIENTE-como-hablar-en-una-entrevista-de-trabajo","Como hablar en una entrevista de trabajo","Soft Skills",4,3,1,1),
("PENDIENTE-mente-millonaria","Mente Millonaria","Soft Skills",1,1,1,1),
("PENDIENTE-liderazgo-efectivo","Liderazgo Efectivo","Soft Skills",1,1,1,1),
("PENDIENTE-estrategias-de-negociacion","Estrategias de Negociacion","Soft Skills",1,1,1,1),
("7-herramientas-de-la-calidad","7 Herramientas de la Calidad","Ingenieria de Calidad",8,16,3,3),
("metodologia-scrum","Metodologia SCRUM","Ingenieria de Calidad",7,12,2,3),
("poka-yoke","Poka Yoke","Ingenieria de Calidad",5,8,2,2),
("sistema-andon","Sistema Andon","Ingenieria de Calidad",4,6,2,2),
("kaizen-mejora-continua","Kaizen - Mejora Continua","Kaizen y Mejora Continua",8,16,2,3),
("metodologia-5-s","Metodologia 5'S","Kaizen y Mejora Continua",5,8,1,2),
("oee","OEE","Kaizen y Mejora Continua",5,8,2,3),
("foda","FODA","Kaizen y Mejora Continua",4,6,1,4),
("gestion-de-inventarios","Gestion de Inventarios","Cadena de Suministros",7,12,3,4),
("hoshin-kanri-planeacion-estrategica","Hoshin Kanri - Planeacion Estrategica","Solucion de Problemas",7,16,2,4),
("solucion-de-problemas-8-d-s","Solucion de Problemas 8 D's","Solucion de Problemas",6,12,2,3),
("solucion-de-problemas-para-ingenieros","Solucion de Problemas para Ingenieros","Solucion de Problemas",6,12,3,3),
("autocad-completo","AutoCAD Completo","Diseno Industrial",18,36,3,2),
("autocad-basico","AutoCAD Basico","Diseno Industrial",9,16,3,1),
("autocad-intermedio","AutoCAD Intermedio","Diseno Industrial",9,16,3,1),
("autocad-avanzado","AutoCAD Avanzado","Diseno Industrial",9,16,3,2),
("solidworks-completo","SolidWorks Completo","Diseno Industrial",16,36,3,2),
("solidworks-basico","SolidWorks Basico","Diseno Industrial",9,16,3,1),
("solidworks-intermedio","SolidWorks Intermedio","Diseno Industrial",9,16,3,1),
("solidworks-avanzado","SolidWorks Avanzado","Diseno Industrial",9,16,4,2),
("microsoft-excel-completo","Microsoft Excel Completo","Microsoft Excel",18,36,3,2),
("microsoft-excel-basico","Microsoft Excel Basico","Microsoft Excel",8,12,1,1),
("microsoft-excel-intermedio","Microsoft Excel Intermedio","Microsoft Excel",9,12,3,1),
("microsoft-excel-avanzado","Microsoft Excel Avanzado","Microsoft Excel",10,12,3,2),
("control-estadistico-de-procesos-spc","Control Estadistico de Procesos (SPC)","Core Tools",8,16,4,3),
("amef","AMEF","Core Tools",7,12,3,3),
("ppap","PPAP","Core Tools",6,10,3,4),
("plan-de-control","Plan de Control","Core Tools",5,8,3,3),
("iso-9001-2015-auditor-interno","ISO 9001:2015 Auditor Interno","Normas ISO",9,20,3,4),
("iso-19011-2018-auditorias-de-sistemas-de-gestion","ISO 19011:2018 Auditorias de SG","Normas ISO",8,16,3,4),
("sql-server","SQL Server","Analisis de Datos",9,16,3,2),
("power-bi","Power BI","Analisis de Datos",8,12,3,3),
]

out=[]
for s,n,e,l,h,k3,k4 in C:
    k1,k2=c1(h),c2(h,l)
    prom=(k1+k2+k3+k4)/4
    niv=int(math.floor(prom+0.5))
    out.append(dict(slug=s,nombre=n,esp=e,lec=l,h=h,c1=k1,c2=k2,c3=k3,c4=k4,
                    dens=round(h/l,2),prom=round(prom,2),nivel=niv))

print("TOTAL CURSOS:",len(out))
print("TOTAL HORAS BRUTAS:",sum(x['h'] for x in out))
paq=["autocad-completo","solidworks-completo","microsoft-excel-completo"]
print("TOTAL HORAS NETAS (sin paquetes):",sum(x['h'] for x in out if x['slug'] not in paq))
from collections import Counter
print("DISTRIBUCION NIVEL:",dict(sorted(Counter(x['nivel'] for x in out).items())))
esp=Counter(x['esp'] for x in out)
print("\nPOR ESPECIALIDAD:")
for k,v in esp.items():
    hs=sum(x['h'] for x in out if x['esp']==k)
    ls=sum(x['lec'] for x in out if x['esp']==k)
    print(f"  {k}: {v} cursos | {hs} h | {ls} lecciones")
json.dump(out,open(Path(__file__).with_name('cursos.json'),'w',encoding='utf-8'),ensure_ascii=False,indent=1)
print("\n--- TABLA ---")
for x in out:
    print(f"{x['nombre']}|{x['lec']}|{x['h']}|{x['dens']}|{x['c1']}{x['c2']}{x['c3']}{x['c4']}|{x['prom']}|N{x['nivel']}")
