import { db } from '../data/mockDb';

export const responseService = {
  submitTokenResponse(token: string, values: Record<string, 1|2|3|4|5>) {
    const invitation = db.tokens.find((t) => t.token === token);
    if (!invitation) throw new Error('Token inválido');
    if (invitation.usedAt) throw new Error('Este token ya fue utilizado');
    const participant = db.participants.find((p) => p.id === invitation.participantId);
    const form = db.forms.find((f) => f.id === invitation.formId);
    const survey = form && db.surveys.find((s) => s.id === form.surveyId);
    if (!participant || !form || !survey) throw new Error('Datos incompletos');

    const responseId = `resp_${Date.now()}`;
    db.responses.push({
      id: responseId,
      participantId: participant.id,
      institutionId: participant.institutionId,
      projectId: participant.projectId,
      surveyId: survey.id,
      formId: form.id,
      estate: participant.estate,
      submittedAt: new Date().toISOString(),
    });
    Object.entries(values).forEach(([questionId, value]) => {
      db.answers.push({ id: `ans_${questionId}_${Date.now()}`, responseId, questionId, value });
    });
    invitation.usedAt = new Date().toISOString();
    participant.respondedAt = invitation.usedAt;
  },
};
