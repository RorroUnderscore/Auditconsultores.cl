import { db } from '../data/mockDb';
import { Estate, SurveyForm, SurveyQuestion, SurveySection } from '../types/domain';

export const surveyService = {
  createSection(formId: string, title: string) {
    const order = db.sections.filter((s) => s.formId === formId).length + 1;
    const section: SurveySection = { id: `sec_${Date.now()}`, formId, title, order };
    db.sections.push(section);
    return section;
  },
  createLikertQuestion(sectionId: string, text: string) {
    const order = db.questions.filter((q) => q.sectionId === sectionId).length + 1;
    const question: SurveyQuestion = { id: `q_${Date.now()}`, sectionId, text, order, type: 'likert_1_5', required: true };
    db.questions.push(question);
    return question;
  },
  updateFormStatus(formId: string, status: SurveyForm['status']) {
    const form = db.forms.find((f) => f.id === formId);
    if (!form) throw new Error('Formulario no existe');
    form.status = status;
    return form;
  },
  getFormByEstate(surveyId: string, estate: Estate) {
    return db.forms.find((f) => f.surveyId === surveyId && f.estate === estate) || null;
  },
  getQuestionsByForm(formId: string) {
    const sectionIds = db.sections.filter((s) => s.formId === formId).map((s) => s.id);
    return db.questions.filter((q) => sectionIds.includes(q.sectionId)).sort((a,b)=>a.order-b.order);
  },
};
