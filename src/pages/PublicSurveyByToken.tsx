import { useMemo, useState } from 'react';
import { db } from '../data/mockDb';
import { surveyService } from '../services/surveyService';
import { responseService } from '../services/responseService';

export function PublicSurveyByToken({ token }: { token: string }) {
  const invitation = db.tokens.find((t) => t.token === token);
  const participant = invitation && db.participants.find((p) => p.id === invitation.participantId);
  const form = invitation && db.forms.find((f) => f.id === invitation.formId);
  const questions = useMemo(() => (form ? surveyService.getQuestionsByForm(form.id) : []), [form]);
  const [answers, setAnswers] = useState<Record<string, 1|2|3|4|5>>({});
  const [done, setDone] = useState(false);
  const [error, setError] = useState('');

  if (!invitation || !participant || !form) return <div>Token inválido.</div>;
  if (invitation.usedAt) return <div>Esta encuesta ya fue respondida el {new Date(invitation.usedAt).toLocaleString('es-CL')}.</div>;
  if (done) return <div>Gracias, {participant.name}. Tu respuesta fue guardada.</div>;

  const onSubmit = () => {
    const missing = questions.some((q) => !answers[q.id]);
    if (missing) return setError('Debes responder todas las preguntas obligatorias.');
    responseService.submitTokenResponse(token, answers);
    setDone(true);
  };

  return <div>
    <h2>Encuesta {form.estate} · {participant.name}</h2>
    {questions.map((q) => <div key={q.id}><p>{q.text}</p>{[1,2,3,4,5].map((v) => (
      <label key={v}><input type='radio' name={q.id} onChange={() => setAnswers((s) => ({ ...s, [q.id]: v as 1|2|3|4|5 }))} />{v}</label>
    ))}</div>)}
    {error && <p>{error}</p>}
    <button onClick={onSubmit}>Enviar respuestas</button>
  </div>;
}
