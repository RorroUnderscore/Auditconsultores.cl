export type Estate = 'Directivos' | 'Docentes' | 'Apoderados' | 'Paradocentes';
export type SurveyStatus = 'draft' | 'published' | 'closed';
export type QuestionType = 'likert_1_5';

export interface AdminUser { id: string; email: string; role: 'owner' | 'editor' | 'viewer'; }
export interface Institution { id: string; name: string; }
export interface Project { id: string; institutionId: string; name: string; status: 'active' | 'closed'; }
export interface Survey { id: string; projectId: string; name: string; }
export interface SurveyForm { id: string; surveyId: string; estate: Estate; status: SurveyStatus; }
export interface SurveySection { id: string; formId: string; title: string; order: number; }
export interface SurveyQuestion { id: string; sectionId: string; text: string; order: number; type: QuestionType; required: boolean; }
export interface Participant { id: string; institutionId: string; projectId: string; estate: Estate; name: string; email: string; respondedAt?: string; }
export interface InvitationToken { token: string; participantId: string; formId: string; expiresAt?: string; usedAt?: string; }
export interface Response { id: string; participantId: string; institutionId: string; projectId: string; surveyId: string; formId: string; estate: Estate; submittedAt: string; }
export interface ResponseAnswer { id: string; responseId: string; questionId: string; value: 1|2|3|4|5; }
