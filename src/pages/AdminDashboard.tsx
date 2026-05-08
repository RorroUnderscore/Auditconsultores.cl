import { useState } from 'react';
import { db } from '../data/mockDb';
import { surveyService } from '../services/surveyService';
import { reportService } from '../services/reportService';

export function AdminDashboard() {
  const [selectedFormId, setSelectedFormId] = useState(db.forms[0]?.id ?? '');
  const [sectionTitle, setSectionTitle] = useState('');
  const [questionText, setQuestionText] = useState('');
  const participation = reportService.getParticipation('proj_1');

  const addSection = () => {
    if (!sectionTitle) return;
    surveyService.createSection(selectedFormId, sectionTitle);
    setSectionTitle('');
  };
  const firstSection = db.sections.find((s) => s.formId === selectedFormId);
  const addQuestion = () => {
    if (!firstSection || !questionText) return;
    surveyService.createLikertQuestion(firstSection.id, questionText);
    setQuestionText('');
  };

  return <div>
    <h1>Dashboard administrativo</h1>
    <p>Participación: {participation.responded}/{participation.total} ({Math.round(participation.pct * 100)}%)</p>
    <h3>Constructor de encuestas por estamento</h3>
    <select value={selectedFormId} onChange={(e) => setSelectedFormId(e.target.value)}>
      {db.forms.map((f) => <option value={f.id} key={f.id}>{f.estate} · {f.status}</option>)}
    </select>
    <div>
      <input value={sectionTitle} onChange={(e) => setSectionTitle(e.target.value)} placeholder='Nueva dimensión' />
      <button onClick={addSection}>Crear dimensión</button>
    </div>
    <div>
      <input value={questionText} onChange={(e) => setQuestionText(e.target.value)} placeholder='Nueva pregunta Likert 1-5' />
      <button onClick={addQuestion}>Crear pregunta</button>
    </div>
  </div>;
}
