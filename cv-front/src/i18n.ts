export type Locale = 'fr' | 'en'

export function parseLocale(value: unknown): Locale {
  if (value === undefined) return 'fr'
  if (value === 'fr' || value === 'en') return value
  throw new Error('La langue du CV doit être « fr » ou « en ».')
}

const fr = {
  curriculumVitae: 'Curriculum vitae',
  skipToCareer: 'Aller au parcours',
  mainNavigation: 'Navigation principale',
  allCvs: 'Tous les CV',
  career: 'Parcours',
  print: 'Imprimer / PDF',
  expertise: 'Expertise & parcours professionnel',
  professionalLinks: 'Liens professionnels',
  newTab: ' (nouvel onglet)',
  skillsAndEducation: 'Compétences et formation',
  skills: 'Compétences',
  languages: 'Langues',
  education: 'Formation',
  professionalCareer: 'Parcours professionnel',
  experiences: 'Expériences',
  noExperiences: 'Aucune expérience renseignée pour le moment.',
  professionalExperience: 'Expérience professionnelle',
  resources: 'Ressources',
  apiDocumentation: 'Documentation de l’API',
  backToTop: 'Retour en haut',
  consult: 'Consulter le CV',
}

export const messages: Record<Locale, Record<keyof typeof fr, string>> = {
  fr,
  en: {
    curriculumVitae: 'Curriculum vitae',
    skipToCareer: 'Skip to work experience',
    mainNavigation: 'Main navigation',
    allCvs: 'All CVs',
    career: 'Experience',
    print: 'Print / PDF',
    expertise: 'Expertise & professional background',
    professionalLinks: 'Professional links',
    newTab: ' (new tab)',
    skillsAndEducation: 'Skills and education',
    skills: 'Skills',
    languages: 'Languages',
    education: 'Education',
    professionalCareer: 'Professional experience',
    experiences: 'Experience',
    noExperiences: 'No experience listed yet.',
    professionalExperience: 'Professional experience',
    resources: 'Resources',
    apiDocumentation: 'API documentation',
    backToTop: 'Back to top',
    consult: 'View CV',
  },
}
