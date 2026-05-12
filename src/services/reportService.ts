import { db } from '../data/mockDb';

export const reportService = {
  getParticipation(projectId: string) {
    const participants = db.participants.filter((p) => p.projectId === projectId);
    const responded = participants.filter((p) => p.respondedAt).length;
    return { total: participants.length, responded, pct: participants.length ? responded / participants.length : 0 };
  },
  getAverageByQuestion() {
    return db.questions.map((q) => {
      const ans = db.answers.filter((a) => a.questionId === q.id);
      const avg = ans.length ? ans.reduce((s,a)=>s+a.value,0)/ans.length : 0;
      return { questionId: q.id, question: q.text, avg };
    });
  },
};
