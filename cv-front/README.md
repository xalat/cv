# CV Front

Interface React avec TypeScript et Vite.

La page d'accueil `/` liste les CV publics depuis `/api/cvs`.
Chaque carte ouvre `/cv/{id}`, qui charge `/api/cvs/{id}` : parcours,
competences, langues, formation et liens professionnels. Elle gere le chargement,
les erreurs avec nouvelle tentative, les ecrans mobiles et l'impression PDF
via le navigateur. Les experiences avec des cles francaises ou anglaises sont
prises en charge. Aucun jeton ni compte n'est necessaire pour cette lecture.

La langue de chaque CV est definie par son champ API `locale` (`fr` ou `en`).
Le dictionnaire `src/i18n.ts` traduit les rubriques, la navigation, les textes
d'accessibilite et le bouton d'impression ; l'attribut HTML `lang` suit le CV.
Le contenu saisi n'est pas traduit automatiquement. Une ancienne reponse API
sans `locale` reste en francais ; une valeur non prise en charge est rejetee.
L'accueil et les messages avant chargement du CV restent en francais, car aucun
CV ne definit encore leur langue.

## Contact

La page `/contact` propose un formulaire (nom, e-mail, message) qui appelle
`POST /api/contact`. Les liens sont accessibles depuis l'accueil et chaque CV,
y compris sur mobile. L'envoi est synchrone : le bouton et les champs sont
desactives pendant la transmission. Une erreur conserve la saisie, affiche
les erreurs de champs ou du serveur et permet de reessayer ; seul un succes
confirme par l'API vide le formulaire. Le destinataire et les identifiants SMTP
ne figurent jamais dans le frontend. Configurer le backend selon son README.
La page de contact reste en francais, comme l'accueil.

## Serveur local

```bash
cd cv-front
npm ci
npm run dev
```

Ouvrir http://127.0.0.1:5173. Les modifications des composants sont rechargees
automatiquement. Le serveur est limite a la machine locale.

Les appels `/api` sont transmis a Symfony sur http://127.0.0.1:8000 par le proxy
Vite. Demarrer le backend separement pour utiliser ces routes. Dans React,
utiliser des URL relatives, par exemple `fetch('/api/cvs/1')`.
Le chemin `/bundles/apiplatform` est egalement transmis au backend pour charger
les styles, scripts et images de la documentation accessible sur `/api/docs`.

## Commandes

```bash
npm test
npm run lint
npm run build
npm run preview
```

Le proxy est reserve au developpement. En production, configurer le serveur web
pour transmettre `/api` et `/bundles/apiplatform` au backend Symfony et servir `dist/index.html` pour les
routes du frontend, notamment `/cv/{id}` et `/contact`, afin de permettre les liens directs.
