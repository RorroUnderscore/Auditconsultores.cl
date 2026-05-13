import { useState, useEffect, useRef } from "react";
import * as XLSX from "xlsx";
import {
  Building2, FileSpreadsheet, Users, Mail, BarChart3, Database,
  BookOpen, Upload, Eye, Trash2, Edit2, Save, Send, CheckCircle,
  Download, Plus, X, AlertCircle, Clock, PieChart, FolderOpen,
  Copy, Pencil, Sparkles, Loader2, TrendingUp, Moon, Sun,
  PanelLeft, Shield, Bell, History, MessageSquare, PenLine,
  Lightbulb, RefreshCw, LayoutGrid
} from "lucide-react";
import {
  BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, Legend,
  RadarChart, Radar, PolarGrid, PolarAngleAxis, PolarRadiusAxis,
  ResponsiveContainer, Cell
} from "recharts";

// ── Constants ──────────────────────────────────────────────
const EST = ["Directivos","Docentes","Apoderados","Paradocentes"];
const CLR = ["#6366f1","#0ea5e9","#10b981","#f59e0b"];
const ROLES = [{k:"admin",l:"Administrador"},{k:"editor",l:"Editor"},{k:"viewer",l:"Visualizador"}];

const LETTER_TPLS = {
  formal:       { l:"Formal",       t:"Estimado/a Participante:\n\nPor medio de la presente, le invitamos a participar en el Estudio de [INST].\n\nAcceda al cuestionario en:\n[URL]\n\nSus respuestas son confidenciales.\n\nAtentamente,\nEquipo de Gestion Institucional" },
  amigable:     { l:"Amigable",     t:"Hola!\n\nTe invitamos a participar en nuestra encuesta de convivencia de [INST].\n\n[URL]\n\nTu opinion importa! Es anonima y solo toma 5 minutos.\n\nGracias!" },
  recordatorio: { l:"Recordatorio", t:"Estimado/a Participante:\n\nLe recordamos que aun esta pendiente su participacion en el Estudio de [INST].\n\nPor favor acceda aqui:\n[URL]\n\nGracias por su colaboracion." }
};

const QUEST_DIMS = {
  Directivos:   ["Liderazgo Pedagogico","Gestion Institucional","Convivencia Directiva","Comunicacion Interna","Clima Organizacional"],
  Docentes:     ["Practica Pedagogica","Convivencia en el Aula","Trabajo Colaborativo","Satisfaccion Laboral","Relacion con Familias"],
  Apoderados:   ["Participacion Familiar","Comunicacion Escuela-Familia","Satisfaccion General","Percepcion de Seguridad","Inclusion y Diversidad"],
  Paradocentes: ["Integracion al Equipo","Condiciones Laborales","Convivencia con Pares","Comunicacion con Directivos","Satisfaccion en el Rol"],
};

const SURVEY_QS = {
  Directivos: [
    {dim:"Liderazgo Pedagogico",     items:["El equipo directivo promueve practicas pedagogicas innovadoras.","Se realizan retroalimentaciones sistematicas al equipo docente.","La direccion genera condiciones para el desarrollo profesional."]},
    {dim:"Gestion Institucional",    items:["Los procesos administrativos son eficientes y transparentes.","La planificacion considera la participacion de todos los estamentos.","Los recursos se gestionan de forma equitativa."]},
    {dim:"Convivencia Directiva",    items:["Existe respeto y colaboracion en el equipo directivo.","Los conflictos se gestionan de manera oportuna."]},
    {dim:"Comunicacion Interna",     items:["La informacion se comunica de forma clara y oportuna.","Existen canales efectivos de comunicacion institucional."]},
    {dim:"Clima Organizacional",     items:["El ambiente laboral es positivo y motivador.","Me siento orgulloso/a de pertenecer a esta institucion."]},
  ],
  Docentes: [
    {dim:"Practica Pedagogica",      items:["Cuento con recursos para desarrollar mis clases.","Recibo apoyo pedagogico suficiente.","El tiempo para planificar es adecuado."]},
    {dim:"Convivencia en el Aula",   items:["El clima favorece el aprendizaje.","Se establecen acuerdos de convivencia claros."]},
    {dim:"Trabajo Colaborativo",     items:["Existen espacios para el trabajo colaborativo.","Las planificaciones se realizan coordinadamente."]},
    {dim:"Satisfaccion Laboral",     items:["Me siento valorado/a como profesional.","La carga de trabajo es razonable."]},
    {dim:"Relacion con Familias",    items:["Los apoderados participan activamente.","Existe comunicacion fluida con las familias."]},
  ],
  Apoderados: [
    {dim:"Participacion Familiar",         items:["Me siento bienvenido/a en el establecimiento.","El colegio promueve la participacion familiar."]},
    {dim:"Comunicacion Escuela-Familia",   items:["Recibo informacion oportuna sobre mi hijo/a.","Puedo comunicarme facilmente con docentes."]},
    {dim:"Satisfaccion General",           items:["Estoy satisfecho/a con la educacion de mi hijo/a.","Recomendaria este colegio."]},
    {dim:"Percepcion de Seguridad",        items:["Me siento tranquilo/a respecto a la seguridad.","El colegio aborda efectivamente el bullying."]},
    {dim:"Inclusion y Diversidad",         items:["El colegio respeta las diferencias individuales.","Se atiende a estudiantes con necesidades especiales."]},
  ],
  Paradocentes: [
    {dim:"Integracion al Equipo",          items:["Me siento parte de la comunidad educativa.","Existe reconocimiento hacia mi labor."]},
    {dim:"Condiciones Laborales",          items:["Las condiciones fisicas son adecuadas.","Cuento con materiales necesarios.","La carga laboral es razonable."]},
    {dim:"Convivencia con Pares",          items:["Las relaciones son respetuosas.","Los conflictos se resuelven justamente."]},
    {dim:"Comunicacion con Directivos",    items:["Me siento escuchado/a por directivos.","La comunicacion es clara y respetuosa."]},
    {dim:"Satisfaccion en el Rol",         items:["Me siento motivado/a con mi trabajo.","Mi labor contribuye al bienestar escolar."]},
  ],
};

const LIKERT = ["Muy en desacuerdo","En desacuerdo","Neutro","De acuerdo","Muy de acuerdo"];

const RADAR = [
  {dim:"Convivencia", Directivos:85, Docentes:72, Apoderados:68, Paradocentes:55},
  {dim:"Comunicacion",Directivos:78, Docentes:65, Apoderados:71, Paradocentes:60},
  {dim:"Liderazgo",   Directivos:90, Docentes:70, Apoderados:55, Paradocentes:62},
  {dim:"Inclusion",   Directivos:75, Docentes:80, Apoderados:73, Paradocentes:70},
  {dim:"Seguridad",   Directivos:88, Docentes:77, Apoderados:82, Paradocentes:75},
];

const mockScore = (est, dim) => {
  const s = [...est, ...dim].reduce((a, c) => a + c.charCodeAt(0), 0);
  return 52 + (s % 40);
};
const fmtDate = (iso) => new Date(iso).toLocaleDateString("es-CL", {day:"2-digit", month:"2-digit", year:"numeric"});
const INIT_INST = {nombre:"",calle:"",comuna:"",region:"",rNombre:"",rApellido:"",rMail:"",rTel:""};
const INIT_P = {
  Directivos:   [{id:1,nombre:"Ana",   apellido:"Garcia",    mail:"ana.garcia@colegio.cl"},{id:2,nombre:"Carlos",apellido:"Munoz",    mail:"c.munoz@colegio.cl"}],
  Docentes:     [{id:1,nombre:"Maria", apellido:"Lopez",     mail:"m.lopez@colegio.cl"},  {id:2,nombre:"Jose",  apellido:"Perez",     mail:"j.perez@colegio.cl"},{id:3,nombre:"Sofia",apellido:"Torres",mail:"s.torres@colegio.cl"}],
  Apoderados:   [{id:1,nombre:"Luis",  apellido:"Rodriguez", mail:"luis.r@gmail.com"},    {id:2,nombre:"Carmen",apellido:"Vega",      mail:"carmen.v@gmail.com"}],
  Paradocentes: [{id:1,nombre:"Pedro", apellido:"Soto",      mail:"p.soto@colegio.cl"}],
};

const mkProject = (id, name, src) => {
  if (src) {
    const copy = JSON.parse(JSON.stringify(src));
    copy.id = id;
    copy.name = "Copia de " + src.name;
    copy.createdAt = new Date().toISOString();
    copy.status = "En curso";
    copy.signature = null;
    copy.snapshots = [];
    return copy;
  }
  return {
    id, name,
    createdAt: new Date().toISOString(),
    status: "En curso",
    inst: Object.assign({}, INIT_INST),
    q: {Directivos:null, Docentes:null, Apoderados:null, Paradocentes:null},
    pts: JSON.parse(JSON.stringify(INIT_P)),
    log: [], signature: null, snapshots: [], letterTpl: "formal"
  };
};

// ── Dark mode ──────────────────────────────────────────────
const applyDark = (on) => {
  let el = document.getElementById("sdi-dm");
  if (!el) { el = document.createElement("style"); el.id = "sdi-dm"; document.head.appendChild(el); }
  el.textContent = on ? [
    "body{background:#0f172a!important}",
    ".bg-white{background-color:#1e293b!important}",
    ".bg-gray-50{background-color:#0f172a!important}",
    ".bg-gray-100{background-color:#334155!important}",
    ".border-gray-200,.border-gray-100{border-color:#334155!important}",
    ".text-gray-800,.text-gray-700{color:#e2e8f0!important}",
    ".text-gray-600{color:#cbd5e1!important}",
    ".text-gray-500{color:#94a3b8!important}",
    ".text-gray-400{color:#64748b!important}",
    "input,textarea{background-color:#1e293b!important;color:#f1f5f9!important;border-color:#475569!important}",
  ].join("") : "";
};

// ── Storage ────────────────────────────────────────────────
const storeLoad = async (key) => {
  try {
    const r = await window.storage.get(key);
    return r ? JSON.parse(r.value) : null;
  } catch (err) {
    return null;
  }
};
const storeSave = async (key, val) => {
  try {
    await window.storage.set(key, JSON.stringify(val));
  } catch (err) {
    // silent
  }
};

// ── Notifications ──────────────────────────────────────────
const computeNotifs = (projs) => {
  const n = [];
  projs.forEach((p) => {
    if (p.status === "Finalizado") return;
    if (!EST.some((e) => p.q[e])) n.push({id:p.id+"-noq", type:"info", msg:'"'+p.name+'": sin cuestionarios cargados'});
    EST.forEach((est) => {
      const tot = (p.pts[est] || []).length;
      const sent = p.log.filter((l) => l.est === est).length;
      if (tot > 0 && sent > 0 && sent / tot < 0.3) {
        n.push({id:p.id+"-"+est, type:"warn", msg:'"'+p.name+'" - '+est+': participacion baja ('+Math.round(sent/tot*100)+'%)'});
      }
    });
  });
  return n;
};

// ── Excel parse ────────────────────────────────────────────
const parseExcelUpload = (file, est) => new Promise((resolve) => {
  const reader = new FileReader();
  reader.onload = (ev) => {
    try {
      const wb = XLSX.read(new Uint8Array(ev.target.result), {type:"array"});
      const match = wb.SheetNames.find((n) => n.toLowerCase().includes(est.toLowerCase().substring(0,4)));
      let dims = QUEST_DIMS[est];
      if (match) {
        const rows = XLSX.utils.sheet_to_json(wb.Sheets[match], {header:1});
        const hdrs = (rows[0] || []).filter(Boolean).map((h) => String(h).substring(0,40));
        if (hdrs.length >= 2) dims = hdrs.slice(0, 5);
      }
      const scores = dims.map((d) => ({dim:d, score:mockScore(est,d)}));
      resolve({name:file.name, url:"https://forms.colegio.cl/"+est.toLowerCase()+"-2025", ts:new Date(), dims, scores, realFile:true, sheets:wb.SheetNames});
    } catch (err) {
      const dims = QUEST_DIMS[est];
      const scores = dims.map((d) => ({dim:d, score:mockScore(est,d)}));
      resolve({name:file.name, url:"https://forms.colegio.cl/"+est.toLowerCase()+"-2025", ts:new Date(), dims, scores, realFile:false});
    }
  };
  reader.readAsArrayBuffer(file);
});

// ── Exports ────────────────────────────────────────────────
const blobDl = (blob, name) => {
  const u = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = u; a.download = name;
  document.body.appendChild(a); a.click();
  document.body.removeChild(a);
  setTimeout(() => URL.revokeObjectURL(u), 5000);
};

const doExportExcel = (project) => {
  try {
    const wb = XLSX.utils.book_new();
    const today = new Date().toLocaleDateString("es-CL");
    const res = [
      ["SISTEMA DE DIAGNOSTICO INSTITUCIONAL"],[""],
      ["Proyecto", project.name],["ID","Proyecto "+project.id],
      ["Estado", project.status],["Exportacion", today],[""],
      ["INSTITUCION"],["Nombre", project.inst.nombre||""],
      ["Direccion", project.inst.calle||""],["Comuna", project.inst.comuna||""],
      ["Region", project.inst.region||""],[""],["RESPONSABLE"],
      ["Nombre", (project.inst.rNombre||"")+" "+(project.inst.rApellido||"")],
      ["Mail", project.inst.rMail||""],["Telefono", project.inst.rTel||""],
      [""],["PARTICIPACION"],
      ["Estamento","Total","Enviados","% Respuesta","Cuestionario"],
    ];
    EST.forEach((e) => {
      const tot = (project.pts[e]||[]).length;
      const sent = project.log.filter((l) => l.est === e).length;
      res.push([e, tot, sent, tot ? Math.round(sent/tot*100)+"%" : "0%", project.q[e] ? "Si" : "No"]);
    });
    const wsR = XLSX.utils.aoa_to_sheet(res);
    wsR["!cols"] = [{wch:25},{wch:42}];
    XLSX.utils.book_append_sheet(wb, wsR, "Resumen");
    EST.forEach((est) => {
      if (!project.q[est]) return;
      const rows = [["CUESTIONARIO - "+est.toUpperCase()],[],["Archivo",project.q[est].name],["URL",project.q[est].url],[],
        ["N","Dimension","Pregunta","Puntaje","Nivel","Interpretacion"]];
      let n = 1;
      SURVEY_QS[est].forEach((sec) => {
        const sc = (project.q[est].scores.find((s) => s.dim === sec.dim) || {score:0}).score;
        const nv = sc>=80?"Alto":sc>=65?"Medio":"Bajo";
        const it = sc>=80?"Fortaleza":sc>=65?"En desarrollo":"Prioritario";
        sec.items.forEach((item) => rows.push([n++, sec.dim, item, sc+"%", nv, it]));
      });
      const ws = XLSX.utils.aoa_to_sheet(rows);
      ws["!cols"] = [{wch:4},{wch:26},{wch:60},{wch:10},{wch:10},{wch:18}];
      XLSX.utils.book_append_sheet(wb, ws, est.substring(0,31));
    });
    const pRows = [["PARTICIPANTES"],[],["Estamento","Nombre","Apellido","Mail","Estado","Tipo","Timestamp"]];
    EST.forEach((est) => {
      (project.pts[est]||[]).forEach((p) => {
        const lg = project.log.filter((l) => l.est===est && l.mail===p.mail);
        const last = lg[lg.length-1] || {};
        pRows.push([est, p.nombre, p.apellido, p.mail, lg.length?"Enviado":"Pendiente", last.type||"", last.ts||""]);
      });
    });
    const pWs = XLSX.utils.aoa_to_sheet(pRows);
    pWs["!cols"] = [{wch:14},{wch:14},{wch:14},{wch:32},{wch:10},{wch:12},{wch:22}];
    XLSX.utils.book_append_sheet(wb, pWs, "Participantes");
    const buf = XLSX.write(wb, {bookType:"xlsx", type:"array"});
    blobDl(new Blob([buf],{type:"application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"}),
      project.name.replace(/\s+/g,"_")+"_P"+project.id+"_"+today.replace(/\//g,"-")+".xlsx");
  } catch (err) {
    alert("Error al exportar Excel: " + err.message);
  }
};

const buildPDFHtml = (project) => {
  const loaded = EST.filter((e) => project.q[e]);
  const today = new Date().toLocaleDateString("es-CL",{year:"numeric",month:"long",day:"numeric"});
  const genTs = new Date().toLocaleString("es-CL");
  const badges = loaded.map((e) => '<span class="badge">'+e+"</span>").join("");
  const respLine = project.inst.rNombre ? "<div>Responsable: "+project.inst.rNombre+" "+(project.inst.rApellido||"")+"</div>" : "";
  const idxItems = ["Introduccion y Metodologia"].concat(loaded.map((e) => "Resultados - "+e)).concat(["Conclusiones"]);
  const idxRows = idxItems.map((t, i) => '<div class="idx-row"><span>'+(i+1)+". "+t+"</span><span>"+(i+2)+"</span></div>").join("");
  const introRows = loaded.map((est) => {
    const ci = EST.indexOf(est);
    const nQ = SURVEY_QS[est].reduce((a, s) => a + s.items.length, 0);
    const sent = project.log.filter((l) => l.est === est).length;
    return "<tr><td style='color:"+CLR[ci]+";font-weight:600'>"+est+"</td><td>"+project.q[est].name+"</td><td>"+nQ+"</td><td>"+((project.pts[est]||[]).length)+"</td><td>"+sent+"</td></tr>";
  }).join("");
  const sections = loaded.map((est, idx) => {
    const ci = EST.indexOf(est); const c = CLR[ci];
    const sc = project.q[est].scores;
    const avg = Math.round(sc.reduce((a, s) => a + s.score, 0) / sc.length);
    const rows = sc.map((s, si) => {
      const n = s.score>=80?"Alto":s.score>=65?"Medio":"Bajo";
      const cls = s.score>=80?"hi":s.score>=65?"me":"lo";
      const it = s.score>=80?"Fortaleza":s.score>=65?"En desarrollo":"Requiere atencion";
      return "<tr><td style='color:#94a3b8'>"+(si+1)+"</td><td style='font-weight:600'>"+s.dim+"</td><td class='"+cls+"'>"+s.score+"%</td><td class='"+cls+"'>"+n+"</td><td>"+it+"</td></tr>";
    }).join("");
    return '<div class="section"><h2 class="sec" style="border-color:'+c+'60;color:'+c+'">'+(idx+2)+". Resultados - "+est+'</h2>'
      +'<p style="color:#64748b;font-size:12px">Instrumento: '+project.q[est].name+' | Promedio: '+avg+'%</p>'
      +"<table><tr><th>#</th><th>Dimension</th><th>Puntaje</th><th>Nivel</th><th>Interpretacion</th></tr>"+rows+"</table></div>";
  }).join("");
  const css = "*{box-sizing:border-box;margin:0;padding:0;}"
    +"body{font-family:Arial,sans-serif;color:#1e293b;font-size:12.5px;line-height:1.7;}"
    +".cover{background:linear-gradient(135deg,#1e1b4b,#1e3a8a);color:#fff;padding:80px 60px;text-align:center;page-break-after:always;}"
    +".cover h1{font-size:28px;font-weight:800;margin-bottom:8px;}"
    +".cover h2{font-size:18px;font-weight:300;opacity:.7;margin-bottom:20px;}"
    +".badges{display:flex;justify-content:center;gap:8px;flex-wrap:wrap;margin-top:16px;}"
    +".badge{padding:4px 14px;border-radius:20px;font-size:11px;background:rgba(255,255,255,.15);}"
    +".content{padding:60px;max-width:860px;margin:0 auto;}"
    +".section{margin-bottom:36px;page-break-inside:avoid;}"
    +"h2.sec{font-size:16px;font-weight:700;border-bottom:3px solid #e0e7ff;padding-bottom:8px;margin:36px 0 12px;}"
    +"p{color:#475569;margin-bottom:10px;}"
    +".idx{background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:20px;margin-bottom:28px;}"
    +".idx-row{display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px dotted #e2e8f0;font-size:12px;color:#475569;}"
    +".idx-row:last-child{border:none;}"
    +"table{width:100%;border-collapse:collapse;margin-top:8px;font-size:11px;}"
    +"th{background:#f1f5f9;border:1px solid #e2e8f0;padding:7px 10px;text-align:left;color:#64748b;font-weight:600;}"
    +"td{border:1px solid #e2e8f0;padding:6px 10px;}"
    +".hi{color:#059669;font-weight:700;}.me{color:#d97706;font-weight:700;}.lo{color:#dc2626;font-weight:700;}"
    +".pbar{position:fixed;bottom:0;left:0;right:0;background:#1e1b4b;padding:12px 24px;display:flex;align-items:center;justify-content:space-between;}"
    +".pbar span{color:rgba(255,255,255,.7);font-size:12px;}"
    +".pbar button{background:#6366f1;color:#fff;border:none;padding:9px 22px;border-radius:8px;cursor:pointer;font-size:13px;font-weight:600;}"
    +"@media print{.pbar{display:none;}}";
  return "<!DOCTYPE html><html lang='es'><head><meta charset='utf-8'><title>Informe - "+project.name+"</title><style>"+css+"</style></head><body>"
    +"<div class='cover'><div style='font-size:9px;text-transform:uppercase;letter-spacing:3px;opacity:.5;margin-bottom:24px'>SDI - Informe Institucional Confidencial</div>"
    +"<h1>Estudio de "+project.name+"</h1>"
    +"<h2>"+(project.inst.nombre||"[Institucion]")+"</h2>"
    +"<div style='width:56px;height:2px;background:rgba(255,255,255,.3);margin:0 auto 20px'></div>"
    +"<div style='font-size:12px;opacity:.55'><div>"+today+"</div>"+respLine
    +"<div style='margin-top:4px'>Proyecto "+project.id+" - "+project.status+"</div></div>"
    +"<div class='badges'>"+badges+"</div></div>"
    +"<div class='content'>"
    +"<div class='idx'><div style='font-weight:700;font-size:12px;text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;color:#1e293b'>Indice</div>"+idxRows+"</div>"
    +"<div class='section'><h2 class='sec'>1. Introduccion y Metodologia</h2>"
    +"<p>El presente informe corresponde al Estudio de <strong>"+project.name+"</strong> aplicado en <strong>"+(project.inst.nombre||"la institucion")+"</strong>. Metodologia 360 con estamentos: <strong>"+loaded.join(", ")+"</strong>.</p>"
    +"<table><tr><th>Estamento</th><th>Instrumento</th><th>Preguntas</th><th>Participantes</th><th>Enviados</th></tr>"+introRows+"</table></div>"
    +sections
    +"<div class='section'><h2 class='sec'>"+(loaded.length+2)+". Conclusiones</h2>"
    +"<p>Los resultados revelan fortalezas y areas de mejora diferenciadas. Se recomienda establecer planes de accion priorizando dimensiones con nivel Bajo o Medio.</p></div>"
    +"<div style='margin-top:40px;padding-top:12px;border-top:1px solid #e2e8f0;font-size:10px;color:#94a3b8;text-align:center'>Referencia: <a href='https://google.com' style='color:#6366f1'>https://google.com</a> - Generado: "+genTs+" - SDI v3.0</div>"
    +"</div><div class='pbar'><span>"+project.name+"</span><button onclick='window.print()'>Imprimir / Guardar PDF</button></div></body></html>";
};

const doExportPDF = (project) => {
  const loaded = EST.filter((e) => project.q[e]);
  if (!loaded.length) { alert("Cargue al menos un cuestionario."); return; }
  try {
    const b = new Blob([buildPDFHtml(project)], {type:"text/html;charset=utf-8"});
    const u = URL.createObjectURL(b);
    const w = window.open(u, "_blank");
    if (!w) { blobDl(b,"Informe_"+project.name.replace(/\s+/g,"_")+".html"); alert("Descargado como HTML. Usalo con Ctrl+P para PDF."); }
    setTimeout(() => URL.revokeObjectURL(u), 60000);
  } catch (err) {
    alert("Error PDF: " + err.message);
  }
};

// ── UI Primitives ───────────────────────────────────────────
function Card({title, icon, action, children}) {
  return (
    <div className="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
      <div className="flex items-center justify-between px-5 py-3.5 border-b border-gray-100">
        <div className="flex items-center gap-2 font-semibold text-sm text-gray-700">
          <span className="text-indigo-500">{icon}</span>{title}
        </div>
        {action}
      </div>
      <div style={{padding:"20px"}}>{children}</div>
    </div>
  );
}

function Fld({label, value, onChange, placeholder, type, disabled}) {
  return (
    <div>
      <label className="block text-xs font-medium text-gray-500 mb-1">{label}</label>
      <input type={type||"text"} value={value} onChange={onChange} placeholder={placeholder} disabled={disabled}
        className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300 disabled:opacity-50" />
    </div>
  );
}

function Btn({children, v, s, icon, onClick, disabled}) {
  const vmap = {primary:"bg-indigo-600 text-white hover:bg-indigo-700", ghost:"bg-gray-100 text-gray-700 hover:bg-gray-200", red:"bg-red-50 text-red-600 hover:bg-red-100", ai:"bg-gradient-to-r from-violet-600 to-indigo-600 text-white hover:opacity-90"};
  const smap = {md:"px-4 py-2 text-sm", sm:"px-3 py-1.5 text-xs", xs:"px-2 py-1 text-xs"};
  const vc = vmap[v||"ghost"] || vmap.ghost;
  const sc = smap[s||"md"] || smap.md;
  return (
    <button onClick={onClick} disabled={disabled} className={"inline-flex items-center gap-1.5 font-medium rounded-lg transition-all disabled:opacity-40 "+sc+" "+vc}>
      {icon}{children}
    </button>
  );
}

function Modal({title, onClose, children, wide}) {
  return (
    <div className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" onClick={onClose}>
      <div className={"bg-white rounded-2xl shadow-2xl w-full overflow-hidden "+(wide?"max-w-2xl":"max-w-md")} onClick={(e) => e.stopPropagation()}>
        <div className="flex items-center justify-between px-5 py-4 border-b">
          <span className="font-semibold text-gray-800">{title}</span>
          <button onClick={onClose} className="text-gray-400 hover:text-gray-600"><X size={18}/></button>
        </div>
        <div className="p-5">{children}</div>
      </div>
    </div>
  );
}

// ── Survey Modal ────────────────────────────────────────────
function SurveyModal({est, qData, onClose}) {
  const ci = EST.indexOf(est);
  const color = CLR[ci];
  const sections = SURVEY_QS[est] || [];
  const total = sections.reduce((a, s) => a + s.items.length, 0);
  const [ans, setAns] = useState({});
  const [done, setDone] = useState(false);
  const [hasErr, setHasErr] = useState(false);
  const answered = Object.keys(ans).length;
  const pct = Math.round(answered / total * 100);
  return (
    <div className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-3" onClick={onClose}>
      <div className="bg-white rounded-2xl shadow-2xl w-full max-w-2xl flex flex-col overflow-hidden" style={{maxHeight:"92vh"}} onClick={(e) => e.stopPropagation()}>
        <div className="shrink-0 text-white px-6 pt-5 pb-4" style={{background:"linear-gradient(135deg,"+color+"ee,"+color+"99)"}}>
          <div className="flex justify-between">
            <div>
              <p className="text-xs opacity-60 uppercase tracking-wider mb-1">Cuestionario - {est}</p>
              <h2 className="text-lg font-bold">{qData.name}</h2>
              <p className="text-xs opacity-60">{total} preguntas</p>
            </div>
            <button onClick={onClose} className="text-white/60 hover:text-white"><X size={20}/></button>
          </div>
          {!done && (
            <div className="mt-3">
              <div className="flex justify-between text-xs opacity-60 mb-1"><span>Progreso</span><span>{answered}/{total}</span></div>
              <div className="h-1.5 bg-white/20 rounded-full overflow-hidden">
                <div className="h-full bg-white/60 rounded-full transition-all" style={{width:pct+"%"}} />
              </div>
            </div>
          )}
        </div>
        <div className="flex-1 overflow-y-auto">
          {done ? (
            <div className="flex flex-col items-center justify-center py-16 text-center px-8">
              <div className="w-16 h-16 rounded-full flex items-center justify-center mb-4" style={{backgroundColor:color+"18"}}>
                <CheckCircle size={36} style={{color}} />
              </div>
              <h3 className="text-xl font-bold text-gray-800 mb-2">Gracias por su Participacion!</h3>
              <p className="text-sm text-gray-500">Sus respuestas han sido registradas correctamente.</p>
              <button onClick={() => { setDone(false); setAns({}); }} className="mt-5 text-xs hover:underline" style={{color}}>Reiniciar vista previa</button>
            </div>
          ) : (
            <div>
              <div className="mx-5 mt-4 mb-2 p-3 bg-blue-50 rounded-xl text-xs text-blue-600 flex gap-2">
                <AlertCircle size={14} className="shrink-0 mt-0.5" />
                Responda todas las preguntas. 1=Muy en desacuerdo, 5=Muy de acuerdo.
              </div>
              {sections.map((sec, si) => {
                const base = sections.slice(0, si).reduce((a, s) => a + s.items.length, 0);
                return (
                  <div key={si}>
                    <div className="flex items-center gap-2 px-5 py-2 border-y bg-gray-50">
                      <div className="w-2 h-2 rounded-full" style={{backgroundColor:color}} />
                      <span className="text-xs font-bold text-gray-600 uppercase tracking-wide">{sec.dim}</span>
                    </div>
                    {sec.items.map((item, ii) => {
                      const key = si+"-"+ii;
                      const sel = ans[key];
                      const showErr = hasErr && !sel;
                      return (
                        <div key={ii} className={"px-5 py-4 border-b "+(showErr?"bg-red-50":"bg-white")}>
                          <p className="text-sm text-gray-700 mb-3">
                            <span className="font-bold mr-1 text-xs" style={{color}}>{base+ii+1}.</span>{item}
                          </p>
                          <div className="flex gap-2">
                            {[1,2,3,4,5].map((v) => {
                              const isSel = sel === v;
                              return (
                                <label key={v} className="flex-1 cursor-pointer">
                                  <input type="radio" className="hidden" onChange={() => { setAns((p) => Object.assign({}, p, {[key]:v})); setHasErr(false); }} />
                                  <div className={"flex flex-col items-center gap-1 p-2 rounded-xl border-2 transition-all "+(isSel?"shadow-sm":"border-gray-200")} style={isSel?{borderColor:color,backgroundColor:color+"12"}:{}}>
                                    <div className={"w-7 h-7 rounded-full border-2 flex items-center justify-center text-xs font-bold "+(isSel?"text-white":"border-gray-300 text-gray-400")} style={isSel?{backgroundColor:color}:{}}>{v}</div>
                                    <span className="text-center leading-tight" style={{fontSize:"8px",color:isSel?color:"#9ca3af"}}>{LIKERT[v-1].split(" ")[0]}</span>
                                  </div>
                                </label>
                              );
                            })}
                          </div>
                        </div>
                      );
                    })}
                  </div>
                );
              })}
            </div>
          )}
        </div>
        {!done && (
          <div className="shrink-0 px-5 py-3.5 border-t bg-gray-50 flex items-center justify-between">
            <span className="text-xs text-gray-400">{answered < total ? (total-answered)+" sin responder" : "Todas respondidas"}</span>
            <button onClick={() => { if (answered < total) { setHasErr(true); return; } setDone(true); }}
              className="flex items-center gap-2 px-5 py-2 rounded-xl text-sm font-semibold text-white" style={{backgroundColor:color}}>
              <Send size={14} />Enviar
            </button>
          </div>
        )}
      </div>
    </div>
  );
}

// ── AI helpers ─────────────────────────────────────────────
async function callAI(prompt, system, history) {
  const msgs = (history||[]).concat([{role:"user",content:prompt}]);
  const body = {model:"claude-sonnet-4-20250514", max_tokens:1000, messages:msgs};
  if (system) body.system = system;
  const r = await fetch("https://api.anthropic.com/v1/messages", {
    method:"POST", headers:{"Content-Type":"application/json"}, body:JSON.stringify(body)
  });
  const d = await r.json();
  return d.content[0].text;
}

function AIAnalysis({project}) {
  const [load, setLoad] = useState(false);
  const [result, setResult] = useState("");
  const [err, setErr] = useState("");
  const loaded = EST.filter((e) => project.q[e]);

  const gen = async () => {
    if (!loaded.length) { setErr("Cargue al menos un cuestionario."); return; }
    setLoad(true); setResult(""); setErr("");
    try {
      const scores = loaded.map((e) => ({estamento:e, dimensiones:project.q[e].scores}));
      const part = EST.map((e) => ({estamento:e, total:(project.pts[e]||[]).length, enviados:project.log.filter((l)=>l.est===e).length}));
      const prompt = 'Analiza los resultados del proyecto "'+project.name+'" (institucion: "'+(project.inst.nombre||"no especificada")+'").\n\nPARTICIPACION:\n'+JSON.stringify(part)+'\n\nPUNTAJES:\n'+JSON.stringify(scores)+'\n\nGenera un analisis ejecutivo con estas secciones (usa **TITULO** para cada una):\n**SINTESIS GENERAL**\n**FORTALEZAS**\n**AREAS DE MEJORA**\n**BRECHAS ENTRE ESTAMENTOS**\n**RECOMENDACIONES**\n\nEscribe en parrafos profesionales en espanol.';
      const txt = await callAI(prompt);
      setResult(txt);
    } catch (err2) {
      setErr("Error al conectar con IA.");
    }
    setLoad(false);
  };

  return (
    <Card title="Analisis Ejecutivo con IA" icon={<Sparkles size={18} className="text-violet-500" />}
      action={<Btn v="ai" s="sm" icon={load?<Loader2 size={13} className="animate-spin"/>:<Sparkles size={13}/>} onClick={gen} disabled={load}>{load?"Analizando...":"Generar"}</Btn>}>
      {!result && !load && !err && (
        <div className="text-center py-8">
          <Sparkles size={32} className="mx-auto mb-3 text-indigo-200" />
          <p className="text-sm text-gray-500">La IA analiza puntajes y genera sintesis, fortalezas, brechas y recomendaciones.</p>
          {!loaded.length && <p className="mt-2 text-xs text-amber-600">Cargue cuestionarios primero.</p>}
        </div>
      )}
      {load && <div className="text-center py-8"><Loader2 size={30} className="mx-auto mb-2 text-indigo-400 animate-spin"/><p className="text-sm text-gray-500">Analizando...</p></div>}
      {err && <p className="text-sm text-red-500 bg-red-50 p-3 rounded-lg">{err}</p>}
      {result && (
        <div className="bg-gradient-to-br from-violet-50 to-indigo-50 rounded-xl p-5 text-sm text-gray-700 leading-relaxed">
          {result.split(/(\*\*[^*]+\*\*)/).map((p, i) =>
            p.startsWith("**") ? <span key={i} className="block font-bold text-indigo-700 mt-4 mb-1 first:mt-0">{p.replace(/\*\*/g,"")}</span> : <span key={i}>{p}</span>
          )}
          <div className="mt-3">
            <Btn s="xs" icon={<Download size={11}/>} onClick={() => blobDl(new Blob([result],{type:"text/plain"}), "Analisis_IA_"+project.name.replace(/\s+/g,"_")+".txt")}>Descargar</Btn>
          </div>
        </div>
      )}
    </Card>
  );
}

function AIChat({project}) {
  const initMsg = {role:"assistant", content:'Hola! Soy tu asistente para el proyecto "'+project.name+'". Preguntame sobre resultados, dimensiones o recomendaciones.'};
  const [msgs, setMsgs] = useState([initMsg]);
  const [inp, setInp] = useState("");
  const [load, setLoad] = useState(false);
  const ref = useRef(null);
  useEffect(() => { if (ref.current) ref.current.scrollTop = ref.current.scrollHeight; }, [msgs]);

  const send = async () => {
    if (!inp.trim() || load) return;
    const um = {role:"user", content:inp};
    setMsgs((p) => p.concat([um]));
    setInp(""); setLoad(true);
    try {
      const loaded = EST.filter((e) => project.q[e]);
      const ctx = "Proyecto: "+project.name+". Institucion: "+(project.inst.nombre||"no especificada")+". Datos: "+JSON.stringify(loaded.map((e) => ({est:e, scores:project.q[e].scores})));
      const history = msgs.concat([um]).map((m) => ({role:m.role, content:m.content}));
      const sys = "Eres un experto en convivencia escolar. Responde en espanol de forma concisa. Datos: "+ctx;
      const txt = await callAI(inp, sys, history.slice(0,-1));
      setMsgs((p) => p.concat([{role:"assistant", content:txt}]));
    } catch (err) {
      setMsgs((p) => p.concat([{role:"assistant", content:"Error al conectar. Intenta de nuevo."}]));
    }
    setLoad(false);
  };

  return (
    <Card title="Chat con IA" icon={<MessageSquare size={18} className="text-violet-500"/>}>
      <div ref={ref} className="h-52 overflow-y-auto space-y-3 mb-3 pr-1">
        {msgs.map((m, i) => (
          <div key={i} className={"flex "+(m.role==="user"?"justify-end":"")}>
            <div className={"max-w-xs rounded-2xl px-3 py-2 text-xs leading-relaxed "+(m.role==="user"?"bg-indigo-600 text-white rounded-br-none":"bg-gray-100 text-gray-700 rounded-bl-none")}>
              {m.content}
            </div>
          </div>
        ))}
        {load && <div className="flex"><div className="bg-gray-100 rounded-2xl rounded-bl-none px-3 py-2"><Loader2 size={12} className="animate-spin text-indigo-400"/></div></div>}
      </div>
      <div className="flex gap-2">
        <input value={inp} onChange={(e) => setInp(e.target.value)} onKeyDown={(e) => e.key==="Enter" && send()}
          placeholder="Pregunta sobre los resultados..." className="flex-1 border border-gray-200 rounded-xl px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-indigo-300" />
        <button onClick={send} disabled={!inp.trim()||load} className="bg-indigo-600 text-white rounded-xl px-3 py-2 disabled:opacity-40 hover:bg-indigo-700"><Send size={13}/></button>
      </div>
    </Card>
  );
}

// ── Heatmap ────────────────────────────────────────────────
function Heatmap({q}) {
  const loaded = EST.filter((e) => q[e]);
  if (!loaded.length) return null;
  const allDims = [...new Set(loaded.flatMap((e) => q[e].dims))];
  const getClr = (s) => !s ? "#f1f5f9" : s>=80 ? "#10b981" : s>=65 ? "#f59e0b" : "#ef4444";
  const getTxt = (s) => !s ? "#9ca3af" : "#fff";
  return (
    <Card title="Mapa de Calor" icon={<LayoutGrid size={18}/>}>
      <div className="overflow-auto">
        <table className="w-full text-xs" style={{borderCollapse:"separate",borderSpacing:"2px"}}>
          <thead>
            <tr>
              <th className="text-left py-2 pr-3 text-gray-500 font-medium" style={{minWidth:150}}>Dimension</th>
              {loaded.map((e) => <th key={e} className="py-2 px-3 text-center font-bold" style={{color:CLR[EST.indexOf(e)],minWidth:90}}>{e}</th>)}
            </tr>
          </thead>
          <tbody>
            {allDims.map((dim) => (
              <tr key={dim}>
                <td className="py-1.5 pr-3 text-gray-600 font-medium whitespace-nowrap">{dim}</td>
                {loaded.map((e) => {
                  const s = (q[e].scores||[]).find((sc) => sc.dim===dim);
                  const v = s ? s.score : null;
                  return (
                    <td key={e} style={{backgroundColor:getClr(v),color:getTxt(v),textAlign:"center",padding:"6px 8px",fontWeight:"bold",fontSize:"11px",borderRadius:"4px"}}>
                      {v ? v+"%" : "-"}
                    </td>
                  );
                })}
              </tr>
            ))}
          </tbody>
        </table>
        <div className="flex items-center gap-4 mt-3 text-xs text-gray-400">
          <span>Leyenda:</span>
          {[["#10b981",">=80% Alto"],["#f59e0b","65-79% Medio"],["#ef4444","<65% Bajo"]].map(([c,l]) => (
            <span key={l} className="flex items-center gap-1">
              <span style={{display:"inline-block",width:10,height:10,borderRadius:2,backgroundColor:c}} />{l}
            </span>
          ))}
        </div>
      </div>
    </Card>
  );
}

// ── Signature ──────────────────────────────────────────────
function SignatureModal({project, onSign, onClose}) {
  const [name, setName] = useState((project.inst.rNombre||"")+" "+(project.inst.rApellido||""));
  const [ok, setOk] = useState(false);
  return (
    <Modal title="Firma de Aprobacion del Reporte" onClose={onClose}>
      <p className="text-sm text-gray-600 mb-4">Al firmar, certifica que los datos son correctos y aprueba su publicacion.</p>
      <div className="space-y-3 mb-4">
        <Fld label="Nombre del firmante" value={name} onChange={(e) => setName(e.target.value)} placeholder="Nombre Apellido" />
        <div className="text-xs text-gray-400">Fecha: {new Date().toLocaleString("es-CL")}</div>
        <label className="flex items-center gap-2 cursor-pointer">
          <input type="checkbox" checked={ok} onChange={(e) => setOk(e.target.checked)} />
          <span className="text-xs text-gray-600">Certifico que los datos son veridicos y apruebo este informe</span>
        </label>
      </div>
      <div className="flex gap-3">
        <Btn onClick={onClose}>Cancelar</Btn>
        <Btn v="primary" disabled={!name.trim()||!ok} onClick={() => onSign({name:name.trim(),date:new Date().toLocaleString("es-CL")})} icon={<PenLine size={13}/>}>Firmar</Btn>
      </div>
    </Modal>
  );
}

// ── Onboarding ─────────────────────────────────────────────
function OnboardingWizard({onComplete}) {
  const [step, setStep] = useState(0);
  const [projName, setProjName] = useState("Convivencia Escolar");
  const modules = [
    ["1. Datos Institucionales","Configura la institucion y responsable"],
    ["2. Cuestionarios","Carga archivos Excel por estamento"],
    ["3. Participantes","Gestiona las bases de datos"],
    ["4. Comunicaciones","Envia cartas con links dinamicos"],
    ["5. Participacion","Dashboard de avance en tiempo real"],
    ["6. Resultados","Exporta datos y graficas"],
    ["7. Entregable","Reporte PDF + analisis con IA"],
    ["8. Benchmarking","Compara proyectos entre si"],
  ];
  const steps = [
    { title:"Bienvenido al SDI", body:(
      <div className="text-center py-4">
        <div className="w-16 h-16 rounded-2xl bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center mx-auto mb-3"><BookOpen size={36} className="text-white"/></div>
        <h3 className="text-lg font-bold text-gray-800 mb-2">Sistema de Diagnostico Institucional</h3>
        <p className="text-sm text-gray-500 max-w-xs mx-auto">Plataforma para gestionar encuestas, analizar resultados y generar reportes con IA.</p>
      </div>
    )},
    { title:"Nombra tu primer proyecto", body:(
      <div>
        <p className="text-sm text-gray-600 mb-3">Sobre que tematica sera tu diagnostico?</p>
        <Fld label="Nombre del proyecto" value={projName} onChange={(e) => setProjName(e.target.value)} placeholder="Ej: Convivencia Escolar, Liderazgo..." />
        <div className="mt-3 grid grid-cols-2 gap-2">
          {["Convivencia Escolar","Liderazgo","Clima Laboral","Inclusion"].map((n) => (
            <button key={n} onClick={() => setProjName(n)} className={"text-xs px-3 py-1.5 rounded-lg border transition-colors "+(projName===n?"bg-indigo-600 text-white border-indigo-600":"border-gray-200 text-gray-600 hover:border-indigo-300")}>{n}</button>
          ))}
        </div>
      </div>
    )},
    { title:"Tus 8 modulos", body:(
      <div className="space-y-2">
        {modules.map((m) => (
          <div key={m[0]} className="flex items-start gap-2 text-xs">
            <CheckCircle size={13} className="text-indigo-500 mt-0.5 shrink-0"/>
            <div><span className="font-semibold text-gray-700">{m[0]}</span><span className="text-gray-400"> - {m[1]}</span></div>
          </div>
        ))}
      </div>
    )},
  ];
  const cur = steps[step];
  return (
    <div className="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4">
      <div className="bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden">
        <div className="bg-gradient-to-r from-indigo-600 to-violet-600 px-5 py-4">
          <div className="flex justify-between items-center">
            <h3 className="text-white font-bold">{cur.title}</h3>
            <span className="text-white/50 text-xs">{step+1}/{steps.length}</span>
          </div>
          <div className="flex gap-1 mt-2">
            {steps.map((_, i) => <div key={i} className={"h-1 rounded-full flex-1 "+(i<=step?"bg-white":"bg-white/30")} />)}
          </div>
        </div>
        <div className="p-5">{cur.body}</div>
        <div className="px-5 pb-5 flex gap-3">
          {step > 0 && <Btn onClick={() => setStep((s) => s-1)}>Anterior</Btn>}
          <button onClick={() => { if (step < steps.length-1) setStep((s) => s+1); else onComplete(projName.trim()||"Convivencia Escolar"); }}
            className="flex-1 bg-indigo-600 text-white rounded-xl py-2.5 text-sm font-semibold hover:bg-indigo-700 transition-colors">
            {step < steps.length-1 ? "Siguiente" : "Comenzar"}
          </button>
        </div>
      </div>
    </div>
  );
}

// ── Snapshot History ───────────────────────────────────────
function SnapshotModal({project, onClose}) {
  const snaps = project.snapshots || [];
  return (
    <Modal title="Historial de Versiones" onClose={onClose} wide>
      {!snaps.length ? (
        <p className="text-sm text-gray-400 text-center py-6">Sin versiones guardadas.</p>
      ) : (
        <div className="space-y-3 max-h-80 overflow-y-auto">
          {snaps.map((s, i) => (
            <div key={i} className="border rounded-xl p-3">
              <div className="flex items-center justify-between mb-2">
                <span className="font-semibold text-sm text-gray-700">{s.label}</span>
                <span className="text-xs text-gray-400">{fmtDate(s.date)}</span>
              </div>
              <div className="flex flex-wrap gap-1">
                {Object.keys(s.scores||{}).map((est) => {
                  const ci = EST.indexOf(est);
                  const sc = s.scores[est] || [];
                  const avg = sc.length ? Math.round(sc.reduce((a,d) => a+d.score,0)/sc.length) : 0;
                  return <span key={est} className="text-xs px-2 py-0.5 rounded-full font-medium" style={{backgroundColor:CLR[ci]+"18",color:CLR[ci]}}>{est}: {avg}%</span>;
                })}
              </div>
            </div>
          ))}
        </div>
      )}
    </Modal>
  );
}

// ── AI Suggestions ─────────────────────────────────────────
function AISuggestions({est, onClose}) {
  const [load, setLoad] = useState(true);
  const [suggs, setSuggs] = useState([]);
  useEffect(() => {
    const prompt = "Sugiere 6 preguntas Likert (1-5) para convivencia escolar, estamento "+est+". Dimensiones: "+QUEST_DIMS[est].join(", ")+". Solo las preguntas, una por linea, sin numeracion.";
    callAI(prompt)
      .then((t) => setSuggs(t.split("\n").filter((l) => l.trim())))
      .catch(() => setSuggs(["Error al conectar con IA."]))
      .finally(() => setLoad(false));
  }, [est]);
  return (
    <Modal title={"Sugerencias IA - "+est} onClose={onClose}>
      {load ? (
        <div className="text-center py-8"><Loader2 size={28} className="mx-auto mb-2 text-indigo-400 animate-spin"/><p className="text-sm text-gray-500">Generando preguntas...</p></div>
      ) : (
        <div className="space-y-2">
          {suggs.map((s, i) => (
            <div key={i} className="flex items-start gap-2 text-sm text-gray-700 bg-gray-50 rounded-lg p-2.5">
              <Lightbulb size={13} className="text-amber-400 shrink-0 mt-0.5"/>{s}
            </div>
          ))}
        </div>
      )}
    </Modal>
  );
}

// ── Module 1 ────────────────────────────────────────────────
function M1({inst, setInst, role}) {
  const ro = role === "viewer";
  const f = (k) => (e) => setInst((p) => Object.assign({}, p, {[k]:e.target.value}));
  return (
    <div className="space-y-4">
      <Card title="Datos de la Institucion" icon={<Building2 size={18}/>}>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <Fld label="Nombre de la Institucion" value={inst.nombre} onChange={f("nombre")} placeholder="Colegio San Patricio" disabled={ro}/>
          <Fld label="Calle" value={inst.calle} onChange={f("calle")} placeholder="Av. Principal 1234" disabled={ro}/>
          <Fld label="Comuna" value={inst.comuna} onChange={f("comuna")} placeholder="Las Condes" disabled={ro}/>
          <Fld label="Region" value={inst.region} onChange={f("region")} placeholder="Region Metropolitana" disabled={ro}/>
        </div>
      </Card>
      <Card title="Responsable del Estudio" icon={<Users size={18}/>}>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <Fld label="Nombre" value={inst.rNombre} onChange={f("rNombre")} placeholder="Juan" disabled={ro}/>
          <Fld label="Apellidos" value={inst.rApellido} onChange={f("rApellido")} placeholder="Gonzalez" disabled={ro}/>
          <Fld label="Mail" value={inst.rMail} onChange={f("rMail")} placeholder="juan@colegio.cl" type="email" disabled={ro}/>
          <Fld label="Telefono" value={inst.rTel} onChange={f("rTel")} placeholder="+56 9 1234 5678" disabled={ro}/>
        </div>
        {!ro && <div className="mt-4 flex justify-end"><Btn v="primary" icon={<Save size={13}/>}>Guardar</Btn></div>}
      </Card>
    </div>
  );
}

// ── Module 2 ────────────────────────────────────────────────
function M2({q, setQ, role}) {
  const ro = role === "viewer";
  const [drag, setDrag] = useState(null);
  const [prev, setPrev] = useState(null);
  const [aiEst, setAiEst] = useState(null);
  const [loading, setLoading] = useState(null);

  const upload = async (est, file) => {
    setLoading(est);
    const data = await parseExcelUpload(file, est);
    setQ((p) => Object.assign({}, p, {[est]:data}));
    setLoading(null);
  };

  return (
    <div className="space-y-4">
      {EST.map((est, i) => (
        <Card key={est} title={est} icon={<FileSpreadsheet size={18} color={CLR[i]}/>}>
          {q[est] ? (
            <div>
              <div className="flex items-center justify-between bg-gray-50 rounded-xl p-3 mb-3">
                <div className="flex items-center gap-3">
                  <div className="w-8 h-8 rounded-lg flex items-center justify-center" style={{backgroundColor:CLR[i]+"22"}}>
                    <CheckCircle size={16} color={CLR[i]}/>
                  </div>
                  <div>
                    <p className="text-sm font-medium text-gray-800">{q[est].name}{q[est].realFile && <span className="ml-2 text-xs text-green-600">Archivo real</span>}</p>
                    <a href={q[est].url} target="_blank" className="text-xs text-indigo-500 hover:underline">{q[est].url}</a>
                  </div>
                </div>
                <div className="flex gap-1.5">
                  <Btn s="xs" v="primary" icon={<Eye size={11}/>} onClick={() => setPrev(est)}>Ver</Btn>
                  {!ro && <Btn s="xs" icon={<Lightbulb size={11}/>} onClick={() => setAiEst(est)}>IA</Btn>}
                  {!ro && <Btn s="xs" v="red" icon={<Trash2 size={11}/>} onClick={() => setQ((p) => Object.assign({}, p, {[est]:null}))}>Eliminar</Btn>}
                </div>
              </div>
              <div className="flex flex-wrap gap-1.5">
                {q[est].dims.map((d, di) => (
                  <span key={di} className="text-xs px-2.5 py-1 rounded-full border font-medium" style={{borderColor:CLR[i]+"60",color:CLR[i],backgroundColor:CLR[i]+"11"}}>{d}</span>
                ))}
              </div>
            </div>
          ) : ro ? (
            <p className="text-xs text-gray-400 text-center py-4">Sin cuestionario cargado</p>
          ) : (
            <label
              onDragOver={(e) => { e.preventDefault(); setDrag(est); }}
              onDragLeave={() => setDrag(null)}
              onDrop={(e) => { e.preventDefault(); setDrag(null); if (e.dataTransfer.files[0]) upload(est, e.dataTransfer.files[0]); }}
              className={"flex flex-col items-center py-8 border-2 border-dashed rounded-xl cursor-pointer transition-all "+(drag===est?"border-indigo-400 bg-indigo-50":"border-gray-200 hover:border-indigo-300 hover:bg-gray-50")}>
              {loading===est ? (
                <><Loader2 size={24} className="text-indigo-400 animate-spin mb-2"/><p className="text-xs text-indigo-500">Procesando...</p></>
              ) : (
                <><Upload size={22} className="text-gray-300 mb-2"/><p className="text-xs text-gray-400">Arrastra un Excel real, o</p><span className="text-xs text-indigo-600 font-medium mt-1">selecciona un archivo</span></>
              )}
              <input type="file" accept=".xlsx,.xls" className="hidden" onChange={(e) => { if (e.target.files[0]) upload(est, e.target.files[0]); }}/>
            </label>
          )}
        </Card>
      ))}
      {prev && q[prev] && <SurveyModal est={prev} qData={q[prev]} onClose={() => setPrev(null)}/>}
      {aiEst && <AISuggestions est={aiEst} onClose={() => setAiEst(null)}/>}
    </div>
  );
}

// ── Module 3 ────────────────────────────────────────────────
function M3({pts, setPts, role}) {
  const ro = role === "viewer";
  const [tab, setTab] = useState("Docentes");
  const [edit, setEdit] = useState(null);
  const list = pts[tab] || [];

  const del = (id) => setPts((p) => Object.assign({}, p, {[tab]:p[tab].filter((x) => x.id!==id)}));
  const save = (row) => {
    setPts((p) => {
      const updated = edit==="new" ? p[tab].concat([Object.assign({},row,{id:Date.now()})]) : p[tab].map((x) => x.id===row.id ? row : x);
      return Object.assign({}, p, {[tab]:updated});
    });
    setEdit(null);
  };

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap gap-2">
        {EST.map((e, i) => (
          <button key={e} onClick={() => setTab(e)} className={"px-3 py-1.5 rounded-full text-xs font-medium transition-all "+(tab===e?"text-white":"bg-gray-100 text-gray-600")} style={tab===e?{backgroundColor:CLR[i]}:{}}>
            {e} ({(pts[e]||[]).length})
          </button>
        ))}
      </div>
      <Card title={"Participantes - "+tab} icon={<Users size={18}/>} action={!ro && <Btn v="primary" s="sm" icon={<Plus size={13}/>} onClick={() => setEdit("new")}>Agregar</Btn>}>
        <div className="overflow-auto">
          <table className="w-full text-sm min-w-[480px]">
            <thead>
              <tr className="border-b text-gray-500 text-xs font-medium">
                <th className="text-left py-2 px-2">Nombre</th>
                <th className="text-left py-2 px-2">Apellido</th>
                <th className="text-left py-2 px-2">Mail</th>
                {!ro && <th className="py-2 px-2 text-right">Acciones</th>}
              </tr>
            </thead>
            <tbody>
              {list.map((r) => (
                <tr key={r.id} className="border-b last:border-0 hover:bg-gray-50">
                  {!ro && edit===r.id ? (
                    <ER row={r} onSave={save} onCancel={() => setEdit(null)}/>
                  ) : (
                    <>
                      <td className="py-2 px-2">{r.nombre}</td>
                      <td className="py-2 px-2">{r.apellido}</td>
                      <td className="py-2 px-2 text-gray-400 text-xs">{r.mail}</td>
                      {!ro && (
                        <td className="py-2 px-2 text-right whitespace-nowrap">
                          <Btn s="xs" icon={<Edit2 size={11}/>} onClick={() => setEdit(r.id)}>Editar</Btn>{" "}
                          <Btn s="xs" v="red" icon={<Trash2 size={11}/>} onClick={() => del(r.id)}>Eliminar</Btn>
                        </td>
                      )}
                    </>
                  )}
                </tr>
              ))}
              {!ro && edit==="new" && <ER row={{id:"new",nombre:"",apellido:"",mail:""}} onSave={save} onCancel={() => setEdit(null)}/>}
            </tbody>
          </table>
        </div>
        {!list.length && edit!=="new" && <p className="text-center text-gray-400 py-6 text-sm">Sin participantes</p>}
      </Card>
    </div>
  );
}

function ER({row, onSave, onCancel}) {
  const [d, setD] = useState(row);
  const f = (k) => (e) => setD((p) => Object.assign({}, p, {[k]:e.target.value}));
  const inp = "w-full border rounded px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-indigo-300";
  return (
    <>
      <td className="py-1 px-1"><input className={inp} value={d.nombre} onChange={f("nombre")} placeholder="Nombre"/></td>
      <td className="py-1 px-1"><input className={inp} value={d.apellido} onChange={f("apellido")} placeholder="Apellido"/></td>
      <td className="py-1 px-1"><input className={inp} value={d.mail} onChange={f("mail")} placeholder="mail@cl"/></td>
      <td className="py-1 px-1 text-right whitespace-nowrap">
        <Btn s="xs" v="primary" icon={<Save size={11}/>} onClick={() => onSave(d)}>OK</Btn>{" "}
        <Btn s="xs" icon={<X size={11}/>} onClick={onCancel}>x</Btn>
      </td>
    </>
  );
}

// ── Module 4 ────────────────────────────────────────────────
function M4({q, pts, inst, log, setLog, letterTpl, setLetterTpl, role}) {
  const ro = role === "viewer";
  const link = EST.reduce((a, e) => a || (q[e] && q[e].url), null);
  const mkTxt = (tpl) => {
    const t = LETTER_TPLS[tpl||"formal"].t;
    return t.replace(/\[INST\]/g, inst.nombre||"[Nombre Institucion]").replace(/\[URL\]/g, link||"[URL del Cuestionario]");
  };
  const [txt, setTxt] = useState(mkTxt("formal"));
  const [approved, setApproved] = useState(false);
  const [editing, setEditing] = useState(false);

  const sendGroup = (est, type) => {
    const now = new Date().toLocaleString("es-CL");
    const sentMails = log.filter((l) => l.est===est).map((l) => l.mail);
    const allP = pts[est] || [];
    const targets = type==="recordatorio" ? allP.filter((p) => !sentMails.includes(p.mail)) : allP;
    const rows = targets.map((p) => ({id:Date.now()+Math.random(), dest:p.nombre+" "+p.apellido, mail:p.mail, est, ts:now, type}));
    setLog((prev) => prev.concat(rows));
  };

  return (
    <div className="space-y-4">
      {!ro && (
        <Card title="Plantillas de Carta" icon={<Mail size={18}/>}>
          <div className="flex gap-2">
            {Object.keys(LETTER_TPLS).map((k) => (
              <button key={k} onClick={() => { setLetterTpl(k); setTxt(mkTxt(k)); }}
                className={"px-3 py-1.5 text-xs font-medium rounded-lg border transition-all "+(letterTpl===k?"bg-indigo-600 text-white border-indigo-600":"border-gray-200 text-gray-600 hover:border-indigo-300")}>
                {LETTER_TPLS[k].l}
              </button>
            ))}
          </div>
        </Card>
      )}
      <Card title="Carta de Invitacion" icon={<Mail size={18}/>}
        action={approved ? (
          <span className="flex items-center gap-1 text-green-600 text-xs font-medium"><CheckCircle size={13}/>Aprobada</span>
        ) : !ro ? (
          <div className="flex gap-2">
            <Btn s="sm" icon={<Edit2 size={13}/>} onClick={() => setEditing((e) => !e)}>{editing?"Ver":"Editar"}</Btn>
            <Btn v="primary" s="sm" icon={<CheckCircle size={13}/>} onClick={() => setApproved(true)}>Aprobar</Btn>
          </div>
        ) : null}>
        {editing && !ro ? (
          <textarea value={txt} onChange={(e) => setTxt(e.target.value)} className="w-full border rounded-lg p-3 text-sm font-mono h-44 resize-none focus:outline-none focus:ring-2 focus:ring-indigo-300"/>
        ) : (
          <div className="bg-gray-50 rounded-xl p-4 text-sm whitespace-pre-wrap leading-relaxed text-gray-700 border border-gray-100">{txt}</div>
        )}
        {!link && <p className="mt-2 text-xs text-amber-600 flex items-center gap-1"><AlertCircle size={12}/>Cargue un cuestionario en el Modulo 2.</p>}
      </Card>
      {(approved||ro) && (
        <Card title="Enviar por Estamento" icon={<Send size={18}/>}>
          <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
            {EST.map((est, i) => {
              const cnt = (pts[est]||[]).length;
              const sent = log.filter((l) => l.est===est && l.type!=="recordatorio").length;
              const rem = cnt - sent;
              return (
                <div key={est} className="border rounded-xl p-3 text-center">
                  <div className="w-8 h-8 rounded-full mx-auto mb-1.5 flex items-center justify-center text-white text-xs font-bold" style={{backgroundColor:CLR[i]}}>{cnt}</div>
                  <p className="text-xs font-semibold text-gray-700">{est}</p>
                  <p className="text-xs text-gray-400 mb-2">{sent} enviados</p>
                  <div className="flex flex-col gap-1">
                    {!ro && <Btn v={sent>=cnt&&cnt>0?"ghost":"primary"} s="xs" icon={<Send size={10}/>} onClick={() => sendGroup(est,"inicial")} disabled={cnt===0||sent>=cnt}>{sent>=cnt&&cnt>0?"Enviado":"Enviar"}</Btn>}
                    {!ro && rem>0 && rem<cnt && <Btn s="xs" icon={<RefreshCw size={10}/>} onClick={() => sendGroup(est,"recordatorio")}>Recordar ({rem})</Btn>}
                  </div>
                </div>
              );
            })}
          </div>
        </Card>
      )}
      {log.length>0 && (
        <Card title="Trazabilidad" icon={<Clock size={18}/>}>
          <div className="overflow-auto max-h-52">
            <table className="w-full text-xs min-w-[520px]">
              <thead><tr className="border-b text-gray-500 font-medium">{["Destinatario","Estamento","Mail","Tipo","Estado","Timestamp"].map((h) => <th key={h} className="text-left py-2 px-2">{h}</th>)}</tr></thead>
              <tbody>
                {log.map((x) => (
                  <tr key={x.id} className="border-b last:border-0 hover:bg-gray-50">
                    <td className="py-1.5 px-2 font-medium">{x.dest}</td>
                    <td className="py-1.5 px-2">{x.est}</td>
                    <td className="py-1.5 px-2 text-gray-400">{x.mail}</td>
                    <td className="py-1.5 px-2"><span className={"px-2 py-0.5 rounded-full text-xs "+(x.type==="recordatorio"?"bg-amber-100 text-amber-700":"bg-blue-100 text-blue-700")}>{x.type||"inicial"}</span></td>
                    <td className="py-1.5 px-2"><span className="bg-green-100 text-green-700 px-2 py-0.5 rounded-full">Enviado</span></td>
                    <td className="py-1.5 px-2 text-gray-400">{x.ts}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </Card>
      )}
    </div>
  );
}

// ── Module 5 ────────────────────────────────────────────────
function M5({log, pts, q}) {
  const data = EST.map((e, i) => {
    const total = (pts[e]||[]).length;
    const resp = log.filter((x) => x.est===e).length;
    return {est:e, total, resp, pct:total?Math.round(resp/total*100):0};
  });
  return (
    <div className="space-y-4">
      <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
        {data.map((d, i) => (
          <div key={d.est} className="bg-white border rounded-2xl p-4 text-center shadow-sm">
            <div className="text-3xl font-bold mb-1" style={{color:CLR[i]}}>{d.pct}%</div>
            <div className="text-xs font-semibold text-gray-700">{d.est}</div>
            <div className="text-xs text-gray-400 mt-1">{d.resp}/{d.total}</div>
            <div className="mt-1.5 h-1.5 bg-gray-100 rounded-full overflow-hidden">
              <div className="h-full rounded-full" style={{width:d.pct+"%",backgroundColor:CLR[i]}}/>
            </div>
          </div>
        ))}
      </div>
      <Card title="Participacion por Estamento" icon={<BarChart3 size={18}/>}>
        <ResponsiveContainer width="100%" height={220}>
          <BarChart data={data} margin={{top:5,right:20,bottom:5,left:0}}>
            <CartesianGrid strokeDasharray="3 3" stroke="#f5f5f5"/>
            <XAxis dataKey="est" tick={{fontSize:11}}/><YAxis domain={[0,100]} tick={{fontSize:11}} unit="%"/>
            <Tooltip formatter={(v) => [v+"%","Participacion"]}/>
            <Bar dataKey="pct" radius={[5,5,0,0]}>{data.map((_,i) => <Cell key={i} fill={CLR[i]}/>)}</Bar>
          </BarChart>
        </ResponsiveContainer>
      </Card>
      <Card title="Spider Chart - Resultados por Dimension" icon={<PieChart size={18}/>}>
        <ResponsiveContainer width="100%" height={300}>
          <RadarChart data={RADAR} margin={{top:10,right:40,bottom:10,left:40}}>
            <PolarGrid stroke="#e5e7eb"/>
            <PolarAngleAxis dataKey="dim" tick={{fontSize:10,fill:"#6b7280"}}/>
            <PolarRadiusAxis domain={[0,100]} tick={{fontSize:9}} tickCount={5}/>
            {EST.map((e, i) => <Radar key={e} name={e} dataKey={e} stroke={CLR[i]} fill={CLR[i]} fillOpacity={0.15} strokeWidth={2}/>)}
            <Legend/><Tooltip/>
          </RadarChart>
        </ResponsiveContainer>
      </Card>
      <Heatmap q={q}/>
    </div>
  );
}

// ── Module 6 ────────────────────────────────────────────────
function M6({project}) {
  const [sub, setSub] = useState(1);
  const [tab, setTab] = useState("Docentes");
  const rows = RADAR.flatMap((r, ri) => [1,2,3].map((_, qi) => ({
    id:ri*3+qi+1, dim:r.dim, preg:"Item "+(qi+1)+" - "+r.dim,
    resp:Math.round(r[tab]/20)+"/5", ts:new Date().toLocaleDateString("es-CL")
  })));
  return (
    <div className="space-y-4">
      <div className="flex gap-2 border-b pb-3">
        {["Base de Datos","Graficas"].map((t, i) => (
          <button key={t} onClick={() => setSub(i+1)} className={"px-4 py-2 text-sm font-medium rounded-lg "+(sub===i+1?"bg-indigo-600 text-white":"bg-gray-100 text-gray-600")}>{t}</button>
        ))}
      </div>
      {sub===1 && (
        <Card title="Respuestas por Estamento" icon={<Database size={18}/>} action={<Btn v="primary" s="sm" icon={<Download size={13}/>} onClick={() => doExportExcel(project)}>Exportar Excel</Btn>}>
          <div className="flex flex-wrap gap-2 mb-4">
            {EST.map((e, i) => <button key={e} onClick={() => setTab(e)} className={"px-3 py-1 rounded-full text-xs font-medium "+(tab===e?"text-white":"bg-gray-100 text-gray-600")} style={tab===e?{backgroundColor:CLR[i]}:{}}>{e}</button>)}
          </div>
          <div className="overflow-auto max-h-64">
            <table className="w-full text-xs border-collapse min-w-[480px]">
              <thead><tr className="bg-gray-50 text-gray-600">{["#","Dimension","Item","Respuesta","Fecha"].map((h) => <th key={h} className="border px-3 py-2 text-left font-medium">{h}</th>)}</tr></thead>
              <tbody>{rows.map((r) => <tr key={r.id} className="hover:bg-gray-50"><td className="border px-3 py-1.5 text-gray-400">{r.id}</td><td className="border px-3 py-1.5 font-medium">{r.dim}</td><td className="border px-3 py-1.5">{r.preg}</td><td className="border px-3 py-1.5"><span className="bg-indigo-50 text-indigo-700 px-2 py-0.5 rounded-full">{r.resp}</span></td><td className="border px-3 py-1.5 text-gray-400">{r.ts}</td></tr>)}</tbody>
            </table>
          </div>
        </Card>
      )}
      {sub===2 && (
        <Card title="Comparativo por Dimension" icon={<BarChart3 size={18}/>}>
          <ResponsiveContainer width="100%" height={320}>
            <BarChart data={RADAR} margin={{top:5,right:20,bottom:5,left:0}}>
              <CartesianGrid strokeDasharray="3 3" stroke="#f5f5f5"/>
              <XAxis dataKey="dim" tick={{fontSize:10}}/><YAxis domain={[0,100]} tick={{fontSize:10}}/>
              <Tooltip/><Legend/>
              {EST.map((e, i) => <Bar key={e} dataKey={e} fill={CLR[i]} radius={[4,4,0,0]}/>)}
            </BarChart>
          </ResponsiveContainer>
        </Card>
      )}
    </div>
  );
}

// ── Module 7 ────────────────────────────────────────────────
function M7({project, onSign, onSnapshot, role}) {
  const ro = role === "viewer";
  const loaded = EST.filter((e) => project.q[e]);
  const [showSig, setShowSig] = useState(false);
  const [showHist, setShowHist] = useState(false);
  return (
    <div className="space-y-4">
      <div className="flex gap-2 flex-wrap">
        <Btn v="primary" s="sm" icon={<Download size={13}/>} onClick={() => doExportPDF(project)} disabled={!loaded.length}>Exportar PDF</Btn>
        {!ro && <Btn s="sm" icon={<History size={13}/>} onClick={() => setShowHist(true)}>Historial ({(project.snapshots||[]).length})</Btn>}
        {!ro && <Btn s="sm" icon={<Save size={13}/>} onClick={() => onSnapshot("Snapshot manual")}>Guardar Version</Btn>}
        {!ro && !project.signature && <Btn v="primary" s="sm" icon={<PenLine size={13}/>} onClick={() => setShowSig(true)}>Firmar</Btn>}
        {project.signature && <span className="flex items-center gap-1 text-green-600 text-xs font-medium bg-green-50 px-3 py-1.5 rounded-lg"><CheckCircle size={13}/>Firmado: {project.signature.name}</span>}
      </div>
      {!loaded.length ? (
        <Card title="Reporte Final" icon={<BookOpen size={18}/>}>
          <div className="text-center py-12"><BookOpen size={40} className="mx-auto mb-3 text-gray-200"/><p className="text-sm text-gray-500">Cargue cuestionarios en el Modulo 2</p></div>
        </Card>
      ) : (
        <Card title="Vista Previa del Reporte" icon={<BookOpen size={18}/>}>
          <div className="border rounded-2xl max-h-[500px] overflow-y-auto">
            <div className="bg-gradient-to-br from-indigo-800 to-slate-900 text-white p-8 text-center">
              <p className="text-xs uppercase tracking-widest text-indigo-300 mb-3">Informe Institucional Confidencial</p>
              <h1 className="text-xl font-bold mb-1">Estudio de {project.name}</h1>
              <h2 className="text-sm text-indigo-200 mb-4">{project.inst.nombre||"[Nombre Institucion]"}</h2>
              <div className="flex justify-center gap-2 flex-wrap">
                {loaded.map((e) => { const idx = EST.indexOf(e); return <span key={e} className="text-xs px-3 py-0.5 rounded-full" style={{backgroundColor:CLR[idx]+"55",color:"#e0e7ff"}}>{e}</span>; })}
              </div>
              {project.signature && <div className="mt-3 text-xs text-indigo-300">Firmado: {project.signature.name}</div>}
            </div>
            {loaded.map((est) => {
              const ci = EST.indexOf(est);
              const sc = project.q[est].scores;
              const avg = Math.round(sc.reduce((a,s) => a+s.score, 0)/sc.length);
              return (
                <div key={est} className="p-5 border-b bg-white">
                  <div className="flex items-center gap-2 mb-3">
                    <div className="w-1 h-7 rounded-full" style={{backgroundColor:CLR[ci]}}/>
                    <h3 className="text-sm font-bold text-gray-800">Resultados - {est}</h3>
                    <span className="ml-auto text-xs font-semibold px-2 py-0.5 rounded-full" style={{backgroundColor:CLR[ci]+"18",color:CLR[ci]}}>Promedio: {avg}%</span>
                  </div>
                  <ResponsiveContainer width="100%" height={sc.length*36+20}>
                    <BarChart data={sc} layout="vertical" margin={{top:0,right:30,bottom:0,left:150}}>
                      <CartesianGrid strokeDasharray="3 3" stroke="#f5f5f5" horizontal={false}/>
                      <XAxis type="number" domain={[0,100]} tick={{fontSize:9}} unit="%"/>
                      <YAxis type="category" dataKey="dim" tick={{fontSize:10,fill:"#374151"}} width={145}/>
                      <Tooltip formatter={(v) => [v+"%","Puntaje"]}/>
                      <Bar dataKey="score" radius={[0,4,4,0]} fill={CLR[ci]}>
                        {sc.map((s, i) => <Cell key={i} fill={CLR[ci]} fillOpacity={0.55+0.45*(s.score/100)}/>)}
                      </Bar>
                    </BarChart>
                  </ResponsiveContainer>
                </div>
              );
            })}
            <div className="p-4 bg-gray-50 text-center text-xs text-gray-400">Referencia: <a href="https://google.com" target="_blank" className="text-indigo-500">https://google.com</a></div>
          </div>
        </Card>
      )}
      <AIAnalysis project={project}/>
      <AIChat project={project}/>
      {showSig && <SignatureModal project={project} onSign={(sig) => { onSign(sig); setShowSig(false); }} onClose={() => setShowSig(false)}/>}
      {showHist && <SnapshotModal project={project} onClose={() => setShowHist(false)}/>}
    </div>
  );
}

// ── Module 8: Benchmarking ──────────────────────────────────
function M8({projects}) {
  const eligible = projects.filter((p) => EST.some((e) => p.q[e]));
  const [sel, setSel] = useState(eligible.map((p) => p.id));
  const BCLS = ["#6366f1","#0ea5e9","#10b981","#f59e0b","#ec4899","#8b5cf6"];

  if (eligible.length < 2) return (
    <Card title="Benchmarking entre Proyectos" icon={<TrendingUp size={18}/>}>
      <div className="text-center py-12"><TrendingUp size={40} className="mx-auto mb-3 text-gray-200"/><p className="text-sm text-gray-500">Necesitas al menos 2 proyectos con cuestionarios cargados.</p></div>
    </Card>
  );

  const active = eligible.filter((p) => sel.includes(p.id));
  const barData = active.map((p, i) => {
    const ld = EST.filter((e) => p.q[e]);
    const allScores = ld.flatMap((e) => p.q[e].scores);
    const avg = allScores.length ? Math.round(allScores.reduce((a, s) => a+s.score, 0)/allScores.length) : 0;
    return {name:p.name.substring(0,14), avg, id:p.id};
  });
  const radarData = RADAR.map((r) => {
    const row = {dim:r.dim};
    active.forEach((p) => {
      const ld = EST.filter((e) => p.q[e]);
      const key = p.name.substring(0,12);
      if (!ld.length) { row[key] = 0; return; }
      let total = 0;
      ld.forEach((e) => {
        const found = (p.q[e].scores||[]).find((s) => s.dim===r.dim);
        if (found) total += found.score;
      });
      row[key] = Math.round(total / ld.length);
    });
    return row;
  });

  return (
    <div className="space-y-4">
      <Card title="Seleccionar Proyectos" icon={<TrendingUp size={18}/>}>
        <div className="flex flex-wrap gap-2">
          {eligible.map((p, i) => (
            <button key={p.id} onClick={() => setSel((s) => s.includes(p.id) ? s.filter((x) => x!==p.id) : s.concat([p.id]))}
              className={"flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium border transition-all "+(sel.includes(p.id)?"text-white border-transparent":"border-gray-200 text-gray-600 hover:border-indigo-300")}
              style={sel.includes(p.id)?{backgroundColor:BCLS[i%BCLS.length]}:{}}>
              <span style={{width:8,height:8,borderRadius:"50%",backgroundColor:BCLS[i%BCLS.length],display:"inline-block"}}/>{p.name} (P{p.id})
            </button>
          ))}
        </div>
      </Card>
      {active.length >= 2 && (
        <>
          <Card title="Puntaje Promedio General" icon={<BarChart3 size={18}/>}>
            <ResponsiveContainer width="100%" height={220}>
              <BarChart data={barData} margin={{top:5,right:20,bottom:5,left:0}}>
                <CartesianGrid strokeDasharray="3 3" stroke="#f5f5f5"/>
                <XAxis dataKey="name" tick={{fontSize:11}}/><YAxis domain={[0,100]} tick={{fontSize:11}} unit="%"/>
                <Tooltip formatter={(v) => [v+"%","Promedio"]}/>
                <Bar dataKey="avg" radius={[5,5,0,0]}>{barData.map((_,i) => <Cell key={i} fill={BCLS[i%BCLS.length]}/>)}</Bar>
              </BarChart>
            </ResponsiveContainer>
          </Card>
          <Card title="Comparativo por Dimension" icon={<PieChart size={18}/>}>
            <ResponsiveContainer width="100%" height={300}>
              <RadarChart data={radarData} margin={{top:10,right:50,bottom:10,left:50}}>
                <PolarGrid stroke="#e5e7eb"/>
                <PolarAngleAxis dataKey="dim" tick={{fontSize:10,fill:"#6b7280"}}/>
                <PolarRadiusAxis domain={[0,100]} tick={{fontSize:9}} tickCount={5}/>
                {active.map((p, i) => <Radar key={p.id} name={p.name.substring(0,12)} dataKey={p.name.substring(0,12)} stroke={BCLS[i%BCLS.length]} fill={BCLS[i%BCLS.length]} fillOpacity={0.15} strokeWidth={2}/>)}
                <Legend/><Tooltip/>
              </RadarChart>
            </ResponsiveContainer>
          </Card>
        </>
      )}
    </div>
  );
}

// ── Navigation ──────────────────────────────────────────────
const NAV = [
  {id:1, label:"Datos Institucionales", icon:Building2},
  {id:2, label:"Cuestionarios",         icon:FileSpreadsheet},
  {id:3, label:"Participantes",         icon:Users},
  {id:4, label:"Comunicaciones",        icon:Mail},
  {id:5, label:"Participacion",         icon:BarChart3},
  {id:6, label:"Resultados",            icon:Database},
  {id:7, label:"Entregable",            icon:BookOpen},
  {id:8, label:"Benchmarking",          icon:TrendingUp},
];

// ── App ─────────────────────────────────────────────────────
export default function App() {
  const [projects, setProjects] = useState([mkProject(1,"Convivencia Escolar")]);
  const [activeId, setActiveId] = useState(1);
  const [nextId, setNextId] = useState(2);
  const [mod, setMod] = useState(1);
  const [darkMode, setDarkMode] = useState(false);
  const [sidebarOpen, setSidebarOpen] = useState(true);
  const [role, setRole] = useState("admin");
  const [showNew, setShowNew] = useState(false);
  const [newName, setNewName] = useState("");
  const [delTarget, setDelTarget] = useState(null);
  const [editingId, setEditingId] = useState(null);
  const [editingName, setEditingName] = useState("");
  const [showNotifs, setShowNotifs] = useState(false);
  const [showOnboarding, setShowOnboarding] = useState(false);
  const [storageReady, setStorageReady] = useState(false);

  useEffect(() => {
    (async () => {
      try {
        const d = await storeLoad("sdi_v3");
        if (d) {
          if (d.projects && d.projects.length) setProjects(d.projects);
          if (d.activeId) setActiveId(d.activeId);
          if (d.nextId) setNextId(d.nextId);
          if (d.darkMode) setDarkMode(d.darkMode);
          if (!d.onboarded) setShowOnboarding(true);
        } else {
          setShowOnboarding(true);
        }
      } catch (err) {
        setShowOnboarding(true);
      }
      setStorageReady(true);
    })();
  }, []);

  useEffect(() => {
    if (!storageReady) return;
    storeSave("sdi_v3", {projects, activeId, nextId, darkMode, onboarded:true});
  }, [projects, activeId, nextId, darkMode, storageReady]);

  useEffect(() => { applyDark(darkMode); }, [darkMode]);

  const ap = projects.find((p) => p.id===activeId) || projects[0];

  const upd = (field) => (val) => setProjects((prev) => prev.map((p) => {
    if (p.id !== activeId) return p;
    const v = typeof val==="function" ? val(p[field]) : val;
    return Object.assign({}, p, {[field]:v});
  }));
  const updProj = (fn) => setProjects((prev) => prev.map((p) => p.id===activeId ? fn(p) : p));

  const addProject = () => {
    if (!newName.trim()) return;
    const np = mkProject(nextId, newName.trim());
    setProjects((p) => p.concat([np]));
    setActiveId(nextId); setNextId((n) => n+1); setMod(1); setShowNew(false); setNewName("");
  };
  const dupProject = (pid) => {
    const src = projects.find((p) => p.id===pid);
    if (!src) return;
    const np = mkProject(nextId, src.name, src);
    setProjects((p) => p.concat([np]));
    setActiveId(nextId); setNextId((n) => n+1); setMod(1);
  };
  const delProject = (pid) => {
    setProjects((prev) => {
      const next = prev.filter((p) => p.id !== pid);
      if (!next.length) {
        const f = mkProject(nextId, "Nuevo Proyecto");
        setNextId((n) => n + 1);
        setActiveId(f.id);
        setDelTarget(null);
        return [f];
      }
      if (activeId === pid) setActiveId(next[next.length - 1].id);
      setDelTarget(null);
      return next;
    });
    setMod(1);
  };

  const saveProjName = (id) => {
    if (editingName.trim()) setProjects((prev) => prev.map((p) => p.id===id ? Object.assign({},p,{name:editingName.trim()}) : p));
    setEditingId(null); setEditingName("");
  };
  const toggleStatus = (id) => setProjects((prev) => prev.map((p) => p.id===id ? Object.assign({},p,{status:p.status==="En curso"?"Finalizado":"En curso"}) : p));
  const createSnapshot = (label) => updProj((p) => {
    const scores = {};
    EST.filter((e) => p.q[e]).forEach((e) => { scores[e] = p.q[e].scores; });
    const snap = {id:Date.now(), date:new Date().toISOString(), label, scores};
    return Object.assign({}, p, {snapshots:(p.snapshots||[]).concat([snap])});
  });
  const signProject = (sig) => updProj((p) => Object.assign({}, p, {signature:sig}));

  const notifs = computeNotifs(projects);
  const loaded = EST.filter((e) => ap.q[e]).length;

  const content = {
    1: <M1 inst={ap.inst} setInst={upd("inst")} role={role}/>,
    2: <M2 q={ap.q} setQ={upd("q")} role={role}/>,
    3: <M3 pts={ap.pts} setPts={upd("pts")} role={role}/>,
    4: <M4 q={ap.q} pts={ap.pts} inst={ap.inst} log={ap.log} setLog={upd("log")} letterTpl={ap.letterTpl||"formal"} setLetterTpl={(v) => updProj((p) => Object.assign({},p,{letterTpl:v}))} role={role}/>,
    5: <M5 log={ap.log} pts={ap.pts} q={ap.q}/>,
    6: <M6 project={ap}/>,
    7: <M7 project={ap} onSign={signProject} onSnapshot={createSnapshot} role={role}/>,
    8: <M8 projects={projects}/>,
  }[mod];

  return (
    <div className="flex h-screen bg-gray-50 overflow-hidden" style={{fontFamily:"system-ui,sans-serif"}}>

      {/* Sidebar */}
      {sidebarOpen && (
        <aside className="w-60 bg-indigo-950 flex flex-col shrink-0 shadow-xl">
          <div className="px-3 pt-4 pb-2 border-b border-indigo-800/60 shrink-0">
            <p className="text-indigo-400 text-xs uppercase tracking-widest px-1 mb-2 font-semibold">Sistema de Diagnostico</p>
            <div className="space-y-1 max-h-52 overflow-y-auto">
              {projects.map((p) => {
                const isAct = p.id === activeId;
                const isEd = editingId === p.id;
                return (
                  <div key={p.id} className={"group rounded-xl transition-all "+(isAct?"bg-indigo-700/80":"hover:bg-indigo-900/60")}>
                    <div className="flex items-start gap-2 px-2 pt-2 pb-1 cursor-pointer" onClick={() => { if (!isEd) { setActiveId(p.id); setMod(1); } }}>
                      <FolderOpen size={13} className={"mt-0.5 shrink-0 "+(isAct?"text-indigo-300":"text-indigo-500")}/>
                      <div className="flex-1 min-w-0">
                        {isEd ? (
                          <input autoFocus value={editingName} onChange={(e) => setEditingName(e.target.value)}
                            onBlur={() => saveProjName(p.id)}
                            onKeyDown={(e) => { if (e.key==="Enter") saveProjName(p.id); if (e.key==="Escape") setEditingId(null); }}
                            onClick={(e) => e.stopPropagation()}
                            className="w-full bg-indigo-800 text-white text-xs rounded px-1.5 py-0.5 outline-none border border-indigo-400"/>
                        ) : (
                          <p className={"text-xs font-semibold truncate "+(isAct?"text-white":"text-indigo-300")} title={p.name}>{p.name}</p>
                        )}
                        <div className="flex items-center gap-1 mt-0.5">
                          <span className="text-indigo-500 text-xs">P{p.id}</span>
                          <span className="text-indigo-600 text-xs">·</span>
                          <button onClick={(e) => { e.stopPropagation(); toggleStatus(p.id); }}
                            className={"text-xs px-1.5 py-0 rounded-full font-medium "+(p.status==="En curso"?"bg-green-900/50 text-green-400":"bg-blue-900/50 text-blue-400")}>
                            {p.status}
                          </button>
                        </div>
                        <p className="text-indigo-600 text-xs">{fmtDate(p.createdAt)}</p>
                      </div>
                    </div>
                    <div className="flex gap-0.5 px-2 pb-1.5 opacity-0 group-hover:opacity-100 transition-opacity">
                      <button onClick={(e) => { e.stopPropagation(); setEditingId(p.id); setEditingName(p.name); }} className="flex-1 flex items-center justify-center gap-0.5 text-indigo-400 hover:text-yellow-300 text-xs py-0.5 rounded hover:bg-white/10"><Pencil size={9}/>Editar</button>
                      <button onClick={(e) => { e.stopPropagation(); dupProject(p.id); }} className="flex-1 flex items-center justify-center gap-0.5 text-indigo-400 hover:text-green-300 text-xs py-0.5 rounded hover:bg-white/10"><Copy size={9}/>Copiar</button>
                      <button onClick={(e) => { e.stopPropagation(); setDelTarget(p); }} className="flex-1 flex items-center justify-center gap-0.5 text-indigo-400 hover:text-red-400 text-xs py-0.5 rounded hover:bg-white/10"><Trash2 size={9}/>Borrar</button>
                    </div>
                  </div>
                );
              })}
            </div>
            <button onClick={() => setShowNew(true)} className="mt-2 w-full flex items-center justify-center gap-1.5 text-xs text-indigo-400 hover:text-green-300 font-medium py-1.5 rounded-lg hover:bg-white/10 border border-dashed border-indigo-800 hover:border-green-700/50">
              <Plus size={11}/>Nuevo Proyecto
            </button>
          </div>
          <div className="px-4 py-2 border-b border-indigo-800/40 shrink-0">
            <p className="text-indigo-500 text-xs">Activo</p>
            <p className="text-white text-xs font-bold truncate">{ap.name}</p>
          </div>
          <nav className="flex-1 py-2 overflow-y-auto">
            {NAV.map((n) => {
              const Icon = n.icon;
              const isA = mod === n.id;
              return (
                <button key={n.id} onClick={() => setMod(n.id)}
                  className={"w-full flex items-center gap-3 px-4 py-2.5 text-left transition-all "+(isA?"bg-indigo-700/80 text-white":"text-indigo-300 hover:bg-indigo-900/60 hover:text-white")}>
                  <Icon size={14} className="shrink-0"/>
                  <span className="text-xs font-medium flex-1">{n.label}</span>
                  {n.id===7 && loaded>0 && <span className="text-xs bg-indigo-500 text-white rounded-full w-4 h-4 flex items-center justify-center">{loaded}</span>}
                  {n.id===7 && <Sparkles size={9} className="text-violet-400"/>}
                  {n.id===8 && projects.filter((p) => EST.some((e) => p.q[e])).length >= 2 && <span className="text-xs bg-amber-500 text-white rounded-full w-4 h-4 flex items-center justify-center">{projects.filter((p) => EST.some((e) => p.q[e])).length}</span>}
                  {isA && <div className="w-1.5 h-1.5 rounded-full bg-indigo-300"/>}
                </button>
              );
            })}
          </nav>
          <div className="p-3 border-t border-indigo-800/60 shrink-0">
            <div className="grid grid-cols-3 gap-1.5 text-center">
              <div className="bg-indigo-900/50 rounded-lg py-2"><p className="text-indigo-300 text-sm font-bold">{projects.length}</p><p className="text-indigo-500 text-xs">Proyectos</p></div>
              <div className="bg-indigo-900/50 rounded-lg py-2"><p className="text-indigo-300 text-sm font-bold">{Object.values(ap.pts).reduce((a,v) => a+(v||[]).length, 0)}</p><p className="text-indigo-500 text-xs">Particip.</p></div>
              <div className="bg-indigo-900/50 rounded-lg py-2"><p className="text-indigo-300 text-sm font-bold">{loaded}</p><p className="text-indigo-500 text-xs">Cuestio.</p></div>
            </div>
            <p className="text-indigo-600 text-xs text-center mt-2">SDI v3.0 · {new Date().getFullYear()}</p>
          </div>
        </aside>
      )}

      {/* Main */}
      <div className="flex-1 flex flex-col overflow-hidden">
        <header className="bg-white border-b border-gray-200 px-4 py-3 flex items-center justify-between shrink-0 shadow-sm">
          <div className="flex items-center gap-3">
            <button onClick={() => setSidebarOpen((o) => !o)} className="text-gray-400 hover:text-gray-600 p-1 rounded-lg hover:bg-gray-100"><PanelLeft size={18}/></button>
            <div className="flex items-center gap-2">
              <span className="text-gray-400 text-xs truncate max-w-[100px]">{ap.name}</span>
              <span className="text-gray-300">/</span>
              <span className="text-gray-800 font-semibold text-sm">{(NAV.find((n) => n.id===mod)||{}).label}</span>
              <span className={"text-xs px-2 py-0.5 rounded-full font-medium "+(ap.status==="En curso"?"bg-green-100 text-green-700":"bg-blue-100 text-blue-700")}>{ap.status}</span>
            </div>
          </div>
          <div className="flex items-center gap-2">
            <div className="relative">
              <select value={role} onChange={(e) => setRole(e.target.value)} className="text-xs border border-gray-200 rounded-lg px-2 py-1.5 pr-6 appearance-none bg-white text-gray-600 focus:outline-none">
                {ROLES.map((r) => <option key={r.k} value={r.k}>{r.l}</option>)}
              </select>
              <Shield size={10} className="absolute right-2 top-2 text-gray-400 pointer-events-none"/>
            </div>
            <div className="relative">
              <button onClick={() => setShowNotifs((o) => !o)} className={"relative p-1.5 rounded-lg transition-colors "+(notifs.length?"text-amber-500 bg-amber-50":"text-gray-400 hover:bg-gray-100")}>
                <Bell size={16}/>
                {notifs.length > 0 && <span className="absolute -top-0.5 -right-0.5 bg-red-500 text-white text-xs rounded-full w-4 h-4 flex items-center justify-center font-bold">{notifs.length}</span>}
              </button>
              {showNotifs && (
                <div className="absolute right-0 top-9 w-72 bg-white border border-gray-200 rounded-xl shadow-lg z-30 overflow-hidden">
                  <div className="px-4 py-2.5 border-b flex items-center justify-between">
                    <span className="text-xs font-semibold text-gray-700">Notificaciones</span>
                    <button onClick={() => setShowNotifs(false)} className="text-gray-400 hover:text-gray-600"><X size={14}/></button>
                  </div>
                  {notifs.length ? notifs.map((n) => (
                    <div key={n.id} className={"px-4 py-2.5 border-b last:border-0 text-xs "+(n.type==="warn"?"text-amber-700 bg-amber-50":"text-blue-700 bg-blue-50")}>{n.msg}</div>
                  )) : <p className="px-4 py-4 text-xs text-gray-400 text-center">Sin notificaciones</p>}
                </div>
              )}
            </div>
            <button onClick={() => setDarkMode((d) => !d)} className="p-1.5 rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600">
              {darkMode ? <Sun size={16}/> : <Moon size={16}/>}
            </button>
            {ap.inst.nombre && <span className="text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded-full max-w-[120px] truncate hidden md:block">{ap.inst.nombre}</span>}
            <div className="w-8 h-8 rounded-full bg-indigo-600 flex items-center justify-center text-white text-xs font-bold shadow">
              {ap.inst.rNombre ? ap.inst.rNombre[0].toUpperCase() : "U"}
            </div>
          </div>
        </header>
        <main className="flex-1 overflow-y-auto p-5">
          <div className="max-w-4xl mx-auto">{content}</div>
        </main>
      </div>

      {/* Onboarding */}
      {showOnboarding && (
        <OnboardingWizard onComplete={(name) => {
          setProjects((prev) => prev.map((p, i) => i===0 ? Object.assign({},p,{name}) : p));
          setShowOnboarding(false);
        }}/>
      )}

      {/* Modal: Nuevo Proyecto */}
      {showNew && (
        <div className="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden">
            <div className="bg-gradient-to-r from-indigo-600 to-indigo-700 px-5 py-4 flex items-center justify-between">
              <div><h3 className="text-white font-bold">Nuevo Proyecto</h3><p className="text-indigo-200 text-xs">Proyecto {nextId}</p></div>
              <button onClick={() => { setShowNew(false); setNewName(""); }} className="text-white/60 hover:text-white"><X size={18}/></button>
            </div>
            <div className="p-5">
              <Fld label="Nombre" value={newName} onChange={(e) => setNewName(e.target.value)} placeholder="Ej: Liderazgo, Clima Laboral..."/>
              <p className="text-xs text-gray-400 mt-1 mb-4">Se creara como Proyecto {nextId}</p>
              <div className="flex gap-3">
                <button onClick={() => { setShowNew(false); setNewName(""); }} className="flex-1 px-4 py-2.5 rounded-xl border-2 border-gray-200 text-sm font-semibold text-gray-600">Cancelar</button>
                <button onClick={addProject} disabled={!newName.trim()} className="flex-1 px-4 py-2.5 rounded-xl bg-indigo-600 text-white text-sm font-semibold disabled:opacity-40">Crear</button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Modal: Eliminar */}
      {delTarget && (
        <div className="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden">
            <div className="bg-red-600 px-6 py-5 text-center">
              <div className="w-12 h-12 rounded-full bg-white/20 flex items-center justify-center mx-auto mb-2"><Trash2 size={24} className="text-white"/></div>
              <h3 className="text-lg font-bold text-white">Piensalo bien!</h3>
              <p className="text-red-100 text-xs">Esta accion no se puede deshacer</p>
            </div>
            <div className="p-5">
              <p className="text-sm text-gray-600 text-center mb-4">Eliminar <strong>"{delTarget.name}"</strong>? Se borraran todos sus datos.</p>
              <div className="flex gap-3">
                <button onClick={() => setDelTarget(null)} className="flex-1 px-4 py-2.5 rounded-xl border-2 border-gray-200 text-sm font-semibold text-gray-600">Cancelar</button>
                <button onClick={() => delProject(delTarget.id)} className="flex-1 px-4 py-2.5 rounded-xl bg-red-600 text-white text-sm font-semibold">Si, eliminar</button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}