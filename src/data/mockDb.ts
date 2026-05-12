import { Estate, Institution, InvitationToken, Participant, Project, Response, ResponseAnswer, Survey, SurveyForm, SurveyQuestion, SurveySection } from '../types/domain';

const ESTATES: Estate[] = ['Directivos','Docentes','Apoderados','Paradocentes'];
const likertLabel = ['Muy en desacuerdo','En desacuerdo','Neutro','De acuerdo','Muy de acuerdo'];

export const db = {
  institutions: [{ id: 'inst_1', name: 'Colegio San Martín' }] as Institution[],
  projects: [{ id: 'proj_1', institutionId: 'inst_1', name: 'Diagnóstico 2026', status: 'active' }] as Project[],
  surveys: [{ id: 'sur_1', projectId: 'proj_1', name: 'Encuesta 360 Convivencia' }] as Survey[],
  forms: ESTATES.map((estate) => ({ id: `form_${estate.toLowerCase()}`, surveyId: 'sur_1', estate, status: 'published' as const })) as SurveyForm[],
  sections: [] as SurveySection[],
  questions: [] as SurveyQuestion[],
  participants: [
    { id: 'p1', institutionId: 'inst_1', projectId: 'proj_1', estate: 'Docentes', name: 'María López', email: 'maria@colegio.cl' },
  ] as Participant[],
  tokens: [{ token: 'demo-docente-token', participantId: 'p1', formId: 'form_docentes' }] as InvitationToken[],
  responses: [] as Response[],
  answers: [] as ResponseAnswer[],
  likertLabel,
};

let questionSeeded = false;
export const seedLikertQuestions = () => {
  if (questionSeeded) return;
  questionSeeded = true;
  db.forms.forEach((form, i) => {
    const sectionId = `sec_${form.id}_1`;
    db.sections.push({ id: sectionId, formId: form.id, title: `Dimensión ${i + 1}`, order: 1 });
    for (let q = 1; q <= 3; q++) {
      db.questions.push({
        id: `q_${form.id}_${q}`,
        sectionId,
        text: `Pregunta ${q} para ${form.estate}`,
        order: q,
        type: 'likert_1_5',
        required: true,
      });
    }
  });
};
