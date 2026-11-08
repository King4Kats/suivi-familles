/* SPDX-FileCopyrightText: 2026 Flavien Auvray
 * SPDX-License-Identifier: AGPL-3.0-or-later */
/* App « Familles » (suivi_familles) — version 0.2. Page unique en JavaScript « vanilla » (pas de compilation).

   Écrans (adresse dans le #, pour revenir en arrière ou garder un lien) :
     #accueil                ma journée : mes actions et rendez-vous, points d’attention
     #familles               les familles que je peux voir (toutes pour la direction)
     #famille/12[/onglet]    une fiche : synthese · membres · suivi · actions · equipe · journal
     #nouvelle               créer une famille (direction)
     #journal                qui a consulté / modifié quoi, toutes familles (direction)
     #guide                  mode d’emploi

   AIDE : chaque petit « ? » ouvre une bulle (textes dans AIDE ci-dessous). Les bulles s’ouvrent
   sans redessiner la page, pour ne pas effacer un formulaire en cours de saisie.

   IMPORTANT : ce fichier ne fait qu’AFFICHER. Masquer un bouton ne protège rien ;
   c’est le serveur (lib/Service/FamillesService.php) qui refuse ce qui n’est pas permis. */
// IIFE (Immediately Invoked Function Expression) : on déclare une fonction et on l’appelle
// aussitôt, grâce au « () » de la toute dernière ligne du fichier.
// Intérêt : toutes les variables ci-dessous restent privées à cette fonction. Elles ne
// polluent pas l’objet global window et ne se mélangent pas aux scripts de Nextcloud.
(function () {
    // Mode strict : une variable non déclarée (faute de frappe) devient une erreur,
    // au lieu de créer en silence une variable globale.
    'use strict'
    // La div #sf-app est posée par le gabarit PHP de l’appli. Absente : on n’est pas sur
    // la page Familles, on s’arrête tout de suite.
    var root = document.getElementById('sf-app')
    if (!root) { return }
    // OC est l’objet global fourni par Nextcloud. generateUrl fabrique l’adresse complète
    // de l’appli (par ex. /index.php/apps/suivi_familles).
    var base = OC.generateUrl('/apps/suivi_familles')
    // dataset lit les attributs data-* de la div racine (data-direction, data-uid, data-nom),
    // remplis côté serveur. direction ne sert qu’à l’AFFICHAGE : le serveur revérifie
    // les droits à chaque requête.
    var direction = root.dataset.direction === '1'
    var moi = root.dataset.uid
    var monNom = root.dataset.nom || moi

    // Libellés lisibles des lignes du journal.
    var ACTIONS = {
        consultation: 'Consultation', impression: 'Impression de la fiche', creation: 'Création de la fiche',
        modification: 'Fiche modifiée', suppression: 'Suppression de la famille', cloture: 'Accompagnement clos',
        reouverture: 'Accompagnement rouvert', referent: 'Référent·e désigné·e', membre_ajout: 'Membre ajouté',
        membre_modif: 'Membre modifié', membre_suppr: 'Membre retiré', acces_ajout: 'Accès ouvert',
        acces_modif: 'Accès modifié', acces_retrait: 'Accès retiré', suivi_ajout: 'Intervention notée',
        suivi_modif: 'Intervention modifiée', suivi_suppr: 'Intervention supprimée', action_ajout: 'Action ajoutée',
        action_modif: 'Action modifiée', action_faite: 'Action faite', action_rouverte: 'Action remise à faire',
        action_suppr: 'Action supprimée', objectif_ajout: 'Objectif ajouté', objectif_modif: 'Objectif modifié',
        objectif_suppr: 'Objectif retiré'
    }
    // Listes fixes. STATUTS_OBJECTIF : [valeur enregistrée, libellé affiché]. DUREES : en minutes.
    // ONGLETS : les parties de la fiche, dans l’ordre (sert aussi à valider l’adresse #famille/12/onglet).
    var STATUTS_OBJECTIF = [['en_cours', 'En cours'], ['atteint', 'Atteint'], ['abandonne', 'Abandonné']]
    var DUREES = [15, 30, 45, 60, 90, 120, 180]
    var ONGLETS = ['synthese', 'membres', 'suivi', 'actions', 'equipe', 'journal']

    // ---------- textes des bulles « ? » ----------
    // Chaque entrée : clé → [titre de la bulle, texte]. aide(cle), plus bas, fabrique le « ? ».
    var AIDE = {
        accueil: ['Votre accueil', 'Ce qui vous attend : les actions et rendez-vous qui vous sont confiés. Tout vient des fiches auxquelles vous avez accès.'],
        mesActions: ['Mes actions et rendez-vous', 'Ce qui vous a été confié (ou que vous vous êtes noté) dans l’onglet « À faire » des fiches. Cochez le rond quand c’est fait.'],
        attention: ['Points d’attention', 'Ce que la direction doit régler : familles sans intervenant, sans référent·e, ou dont le consentement n’est pas noté.'],
        referent: ['Référent·e', 'La personne qui coordonne l’accompagnement et que l’on contacte en premier. Elle fait partie de l’équipe de la famille. La direction la choisit dans l’onglet Équipe.'],
        debut: ['Début de l’accompagnement', 'Date du premier contact ou de l’entrée dans le dispositif.'],
        dernierContact: ['Dernier contact', 'Date de la dernière intervention notée dans l’onglet Suivi.'],
        prochaine: ['Prochaine étape', 'L’action ou le rendez-vous à venir le plus proche (onglet « À faire »).'],
        besoins: ['Besoins', 'Les thèmes sur lesquels la famille est accompagnée, plusieurs choix possibles. Ils servent à retrouver les familles (filtre de la liste).'],
        territoire: ['Territoire', 'La communauté de communes où vit la famille.'],
        orientation: ['Orientée par', 'Qui a fait connaître l’association à la famille : médecin, école, services sociaux… Utile pour savoir quels partenaires orientent le plus.'],
        consentement: ['Consentement', 'Date à laquelle la famille a été informée de ce qui est noté sur elle et de qui y a accès, et a donné son accord. C’est obligatoire pour des données de santé (RGPD). Rangez le document signé en lieu sûr.'],
        situation: ['Situation', 'Le contexte utile à toute l’équipe, en quelques lignes. Restez factuel. Ce qui est confidentiel va dans une note privée (onglet Suivi).'],
        objectifs: ['Objectifs', 'Ce que la famille et l’équipe veulent atteindre ensemble, par exemple « Retrouver un sommeil régulier ». Changez le statut quand c’est atteint ou abandonné.'],
        actions: ['À faire', 'Les prochaines étapes : une démarche, un appel, un rendez-vous. Confiez-les à une personne de l’équipe : elle est prévenue (cloche Nextcloud) et les retrouve sur son accueil.'],
        suivi: ['Suivi', 'Chaque intervention auprès de la famille : entretien, visite, appel, atelier… On note la date, le type, la durée et ce qui s’est passé.'],
        type: ['Type d’intervention', 'La forme de l’intervention : entretien, visite, appel…'],
        duree: ['Durée', 'Temps passé avec la famille (facultatif).'],
        prive: ['Note privée', 'Visible seulement par vous et la direction. Pour ce qui ne doit pas être partagé avec le reste de l’équipe, par exemple un élément confié en séance.'],
        membres: ['Membres', 'Les personnes de la famille. Les informations de santé (médecin, points de vigilance) sont visibles par toute l’équipe de la famille.'],
        medecin: ['Professionnels de santé', 'Nom et ville du professionnel qui suit la personne, pour pouvoir se coordonner avec lui (avec l’accord de la famille).'],
        vigilance: ['Points de vigilance', 'Ce que toute personne qui rencontre ce membre doit savoir : allergie, traitement, handicap, situation à risque… Affiché en évidence sur sa carte.'],
        equipe: ['Équipe', 'Les personnes qui ont accès à cette famille. Personne d’autre (sauf la direction) ne peut la voir, ni même savoir qu’elle existe.'],
        peutModifier: ['Peut modifier', 'Coché : la personne peut aussi changer les coordonnées, les membres et les objectifs. Sinon : elle lit la fiche, note des interventions et gère les actions.'],
        journal: ['Journal', 'Qui a consulté, imprimé ou modifié quoi, et quand. Personne ne peut le modifier. Une consultation est notée au plus une fois par quart d’heure et par personne.'],
        cloture: ['Clore l’accompagnement', 'Quand le suivi s’arrête. La fiche reste consultable dans « Closes » et peut être rouverte. Pensez à retirer l’accès des intervenants qui n’en ont plus besoin.'],
        impression: ['Imprimer', 'Imprime toute la fiche, ou l’enregistre en PDF. L’impression est notée au journal. Un papier avec des données de santé se range sous clé et se détruit après usage.'],
        suppression: ['Supprimer définitivement', 'Efface la fiche et tout son contenu : membres, interventions, actions, objectifs. Pour une demande d’effacement de la famille, ou à la fin de la durée de conservation. Le journal garde seulement la trace de la suppression.']
    }

    // ---------- icônes (tracés Material Design, 24 × 24) ----------
    // Chaque valeur est l’attribut d d’un <path> SVG (le tracé). icone(nom) s’en sert.
    var ICONES = {
        home: 'M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z',
        people: 'M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z',
        chart: 'M5 9.2h3V19H5zM10.6 5h2.8v14h-2.8zm5.6 8H19v6h-2.8z',
        history: 'M13 3a9 9 0 0 0-9 9H1l3.89 3.89.07.14L9 12H6c0-3.87 3.13-7 7-7s7 3.13 7 7-3.13 7-7 7c-1.93 0-3.68-.79-4.94-2.06l-1.42 1.42A8.954 8.954 0 0 0 13 21a9 9 0 0 0 0-18zm-1 5v5l4.28 2.54.72-1.21-3.5-2.08V8H12z',
        tune: 'M3 17v2h6v-2H3zM3 5v2h10V5H3zm10 16v-2h8v-2h-8v-2h-2v6h2zM7 9v2H3v2h4v2h2V9H7zm14 4v-2H11v2h10zm-6-4h2V7h4V5h-4V3h-2v6z',
        help: 'M11 18h2v-2h-2v2zm1-16C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm0-14c-2.21 0-4 1.79-4 4h2c0-1.1.9-2 2-2s2 .9 2 2c0 2-3 1.75-3 5h2c0-2.25 3-2.5 3-5 0-2.21-1.79-4-4-4z',
        print: 'M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zm-3 11H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm-1-9H6v4h12V3z',
        edit: 'M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a.996.996 0 0 0 0-1.41l-2.34-2.34a.996.996 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z',
        event: 'M17 12h-5v5h5v-5zM16 1v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19a2 2 0 0 0 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2h-1V1h-2zm3 18H5V8h14v11z',
        flag: 'M14.4 6 14 4H5v17h2v-7h5.6l.4 2h7V6z',
        check: 'M9 16.17 4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z',
        warning: 'M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z',
        star: 'M12 17.27 18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z',
        lock: 'M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z',
        plus: 'M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z',
        download: 'M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z',
        back: 'M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z',
        archive: 'M20.54 5.23l-1.39-1.68C18.88 3.21 18.47 3 18 3H6c-.47 0-.88.21-1.16.55L3.46 5.23C3.17 5.57 3 6.02 3 6.5V19c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V6.5c0-.48-.17-.93-.46-1.27zM12 17.5 6.5 12H10v-2h4v2h3.5L12 17.5zM5.12 5l.81-1h12l.94 1H5.12z',
        phone: 'M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z',
        mail: 'M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4-8 5-8-5V6l8 5 8-5v2z',
        trash: 'M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z'
    }

    // ---------- état ----------
    // S = l’état (« state ») de toute l’appli, dans UN seul objet. Principe de tout le fichier :
    // on modifie S, puis on appelle render(), qui redessine l’écran à partir de S.
    //   vue : écran courant ; reglages, accueil, liste, fiche, journal : données reçues du serveur ;
    //   filtres, filtreSuivi, filtreJournal, voirFaites : choix de l’utilisateur ;
    //   edition : formulaire ouvert, par ex. { type: 'membre', id: 12 } (id null = nouveau) ;
    //   editionApres : formulaire à ouvrir après un changement d’écran ; cloture : panneau « Clore » ouvert.
    var S = {
        vue: 'accueil', reglages: null, accueil: null, liste: null,
        filtres: { q: '', statut: 'actif', territoire: '', besoin: '', referent: '', tri: '' },
        fiche: null, ficheId: null, onglet: 'synthese', edition: null, editionApres: null, cloture: false,
        filtreSuivi: { type: '', membre: '' }, voirFaites: false,
        journal: null, filtreJournal: ''
    }

    // =====================================================================
    // Petits outils
    // =====================================================================
    // api(méthode, chemin, corps) : appelle l’API PHP de l’appli et renvoie une PROMESSE (Promise).
    // La promesse se résout avec la réponse JSON, ou est rejetée (→ .catch) avec une Error lisible.
    // Exemple : api('GET', '/familles').then(function (r) { … }).catch(erreur)
    function api(method, path, body) {
        // En-têtes : requesttoken = jeton CSRF de Nextcloud (OC.requestToken). Sans lui, Nextcloud
        // refuse la requête, car elle pourrait venir d’un autre site. OCS-APIRequest : en-tête
        // habituel des appels d’API Nextcloud.
        var opts = { method: method, headers: { requesttoken: OC.requestToken, 'OCS-APIRequest': 'true' } }
        // Un corps (POST, PUT) part en JSON : JSON.stringify transforme l’objet en texte.
        if (body) { opts.headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(body) }
        // fetch envoie la requête HTTP. .then reçoit la réponse r quand elle arrive.
        // r.ok est faux pour un code d’erreur HTTP (403, 404, 500…).
        return fetch(base + '/api' + path, opts).then(function (r) {
            // Erreur : on tente de lire le message JSON du serveur (r.json() peut lui-même échouer,
            // d’où le .catch qui renvoie {}), puis throw : la promesse est rejetée et le .catch
            // de l’appelant affichera le message.
            if (!r.ok) { return r.json().catch(function () { return {} }).then(function (j) { throw new Error(j.message || 'Erreur ' + r.status) }) }
            // Tout va bien : on renvoie le JSON décodé (r.json() est lui aussi une promesse).
            return r.json()
        })
    }
    // Crée un élément. Le texte passe TOUJOURS par textContent (jamais innerHTML) : une saisie
    // contenant du HTML s’affiche telle quelle au lieu d’être exécutée.
    // el(tag, attrs, children) : raccourci pour document.createElement, utilisé partout.
    // attrs : { class, text, value, style, onclick…, autres attributs } ; children : voir ajouterEnfants.
    // Exemple : el('p', { class: 'sf-indic', text: 'Bonjour' }) → <p class="sf-indic">Bonjour</p>
    function el(tag, attrs, children) {
        var e = document.createElement(tag)
        // Object.keys donne la liste des noms d’attributs, forEach les parcourt un par un.
        // (attrs || {}) évite une erreur quand attrs est absent.
        Object.keys(attrs || {}).forEach(function (k) {
            var v = attrs[k]
            // null ou undefined (v == null couvre les deux), ou false : attribut non ajouté.
            // Pratique pour écrire disabled: peut ? null : true.
            if (v == null || v === false) { return }
            if (k === 'class') { e.className = v }
            else if (k === 'text') { e.textContent = v }
            else if (k === 'value') { e.value = v }
            else if (k === 'style') { e.style.cssText = v }
            // Une clé qui commence par « on » (onclick, onchange…) devient un écouteur :
            // addEventListener('click', fonction).
            else if (k.indexOf('on') === 0) { e.addEventListener(k.slice(2), v) }
            // Sinon : attribut HTML classique. true donne un attribut vide (disabled=""), ce qui suffit.
            else { e.setAttribute(k, v === true ? '' : v) }
        })
        ajouterEnfants(e, children)
        return e
    }
    // Ajoute les enfants à e. Une chaîne devient un nœud texte (aucun HTML interprété),
    // un tableau est parcouru en rappelant la fonction sur lui (récursivité), et null/false
    // sont ignorés : pratique avec les ternaires « condition ? el(…) : null ».
    function ajouterEnfants(e, children) {
        (children || []).forEach(function (c) {
            if (c == null || c === false) { return }
            if (Array.isArray(c)) { ajouterEnfants(e, c) } else { e.appendChild(typeof c === 'string' ? document.createTextNode(c) : c) }
        })
    }
    // icone(nom) : icône SVG tirée de ICONES. Pour du SVG il faut createElementNS (avec l’espace
    // de noms SVG) et non createElement, sinon le navigateur ne le dessine pas.
    // aria-hidden : les lecteurs d’écran ignorent l’icône, purement décorative.
    function icone(nom) {
        var s = document.createElementNS('http://www.w3.org/2000/svg', 'svg')
        s.setAttribute('viewBox', '0 0 24 24'); s.setAttribute('aria-hidden', 'true'); s.setAttribute('class', 'sf-ico')
        var p = document.createElementNS('http://www.w3.org/2000/svg', 'path')
        p.setAttribute('d', ICONES[nom] || ''); s.appendChild(p)
        return s
    }
    // bouton('classe', 'Texte', action, { ico: 'plus', title: … })
    // Fabrique un <button> : classe CSS, texte, fonction appelée au clic, attributs en plus (extra).
    // Object.assign({}, extra) fait une COPIE : on peut retirer ico (delete) sans toucher à l’objet
    // de l’appelant. Le second Object.assign fusionne les attributs par défaut avec extra.
    function bouton(classe, texte, action, extra) {
        extra = Object.assign({}, extra || {})
        var ico = extra.ico
        delete extra.ico
        return el('button', Object.assign({ type: 'button', class: classe, onclick: action }, extra), [ico ? icone(ico) : null, texte ? el('span', { text: texte }) : null])
    }
    // Bouton en deux temps (1er clic : « Confirmer ? » ; 2e dans les 4 s : action). Évite les erreurs de doigt.
    function boutonConfirme(classe, texte, confirmer, action, extra) {
        // arme mémorise « 1er clic fait ». C’est une fermeture (closure) : la fonction du clic,
        // appelée bien plus tard, se souvient des variables b, span et arme de CET appel.
        var b = bouton(classe, texte, null, extra), arme = false
        var span = b.querySelector('span')
        b.addEventListener('click', function () {
            // 1er clic : on arme le bouton et on change son texte ; setTimeout le désarme après 4000 ms.
            if (!arme) {
                arme = true; span.textContent = confirmer; b.classList.add('sf-arme')
                setTimeout(function () { arme = false; span.textContent = texte; b.classList.remove('sf-arme') }, 4000)
                return
            }
            // 2e clic dans le délai : on désactive le bouton (pas de double envoi) et on agit.
            b.disabled = true
            action()
        })
        return b
    }
    // toast(message, estErreur) : petit message temporaire. role alert/status : annoncé par les
    // lecteurs d’écran. 1er setTimeout : animation de sortie ; 2e : on retire l’élément du DOM.
    function toast(msg, estErreur) {
        var t = el('div', { class: 'sf-toast' + (estErreur ? ' sf-toast--err' : ''), text: msg, role: estErreur ? 'alert' : 'status' })
        root.appendChild(t)
        setTimeout(function () { t.classList.add('sf-toast--out') }, estErreur ? 5000 : 3000)
        setTimeout(function () { if (t.parentNode) { t.parentNode.removeChild(t) } }, estErreur ? 5400 : 3400)
    }
    // erreur(e) : affiche une erreur (souvent reçue dans un .catch) dans un toast rouge.
    // String(e) au cas où e ne serait pas un objet Error.
    function erreur(e) { toast(e.message || String(e), true) }

    // ---- Dates. Le serveur échange des dates texte « AAAA-MM-JJ » (format ISO) : on peut
    // les comparer directement comme des chaînes ('2026-01-05' < '2026-02-01'). ----
    // iso(d) : objet Date → texte ISO. getMonth() commence à 0 (janvier), d’où le + 1 ;
    // padStart(2, '0') ajoute le zéro devant (5 → '05').
    function aujourdhui() { return iso(new Date()) }
    function iso(d) { return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0') }
    // jour(iso) : texte → objet Date. On fixe midi (T12:00) pour qu’un décalage horaire
    // (fuseau, heure d’été) ne fasse pas basculer la date sur la veille.
    function jour(isoTxt) { return new Date(isoTxt + 'T12:00:00') }
    // Affichage en français avec toLocaleDateString('fr-FR', options).
    // quand(ts) reçoit un horodatage Unix en SECONDES (PHP), d’où × 1000 (JavaScript compte en ms).
    function dateFr(isoTxt) { return isoTxt ? jour(isoTxt).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' }) : '' }
    function dateLongue(isoTxt) { return jour(isoTxt).toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }) }
    function moisFr(aaaamm) { return jour(aaaamm + '-01').toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' }) }
    function quand(ts) { return new Date(ts * 1000).toLocaleString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) }
    // heureFr('14:30') → '14 h 30', heureFr('09:00') → '9 h'.
    // joursDepuis : jours écoulés jusqu’à aujourd’hui (864e5 ms = un jour ; négatif = dans le futur).
    function heureFr(h) { if (!h) { return '' } var p = h.split(':'); return parseInt(p[0], 10) + ' h' + (p[1] !== '00' ? ' ' + p[1] : '') }
    function joursDepuis(isoTxt) { return Math.round((jour(aujourdhui()) - jour(isoTxt)) / 864e5) }
    // « il y a 12 jours », « demain »…
    function relatif(isoTxt) {
        if (!isoTxt) { return '' }
        var j = joursDepuis(isoTxt)
        if (j === 0) { return 'aujourd’hui' }
        if (j === 1) { return 'hier' }
        if (j === -1) { return 'demain' }
        if (j < 0) { return 'dans ' + (-j) + ' jours' }
        if (j < 45) { return 'il y a ' + j + ' jours' }
        if (j < 365) { return 'il y a ' + Math.round(j / 30.4) + ' mois' }
        var a = Math.floor(j / 365)
        return 'il y a ' + a + ' an' + (a > 1 ? 's' : '')
    }
    // age(naissance) : âge en années ; on retire un an si l’anniversaire n’est pas encore passé.
    function age(isoTxt) {
        if (!isoTxt) { return '' }
        var n = jour(isoTxt), a = new Date(), ans = a.getFullYear() - n.getFullYear()
        if (a.getMonth() < n.getMonth() || (a.getMonth() === n.getMonth() && a.getDate() < n.getDate())) { ans-- }
        return ans >= 0 ? ans + ' an' + (ans > 1 ? 's' : '') : ''
    }
    // duree(minutes) : 90 → '1 h 30', 45 → '45 min'. Ternaire « condition ? si vrai : si faux ».
    function duree(min) {
        if (!min) { return '' }
        var h = Math.floor(min / 60), m = min % 60
        return h ? h + ' h' + (m ? ' ' + String(m).padStart(2, '0') : '') : m + ' min'
    }
    // Petits outils de texte : pluriel(3, 'membre') → '3 membres' ; prenom('Marie Dupont') → 'Marie' ;
    // initiale('Famille Martin') → 'M' (avatar rond) ; capitale('lundi') → 'Lundi'.
    function pluriel(n, mot, mots) { return n + ' ' + (n > 1 ? (mots || mot + 's') : mot) }
    function prenom(nom) { return (nom || '').split(' ')[0] }
    function initiale(nom) { var n = (nom || '').replace(/^famille\s+/i, '').trim(); return (n.charAt(0) || '?').toUpperCase() }
    function capitale(t) { return t.charAt(0).toUpperCase() + t.slice(1) }
    // norm(t) : texte pour la recherche, sans accents et en minuscules. normalize('NFD') sépare
    // la lettre de son accent (é → e + ´), le replace supprime l’accent. « helene » trouve « Hélène ».
    function norm(t) { return String(t || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase() }
    // datalist : suggestions pour un <input list=…>. pastille : étiquette colorée (ton : gris, alerte, acc…).
    function datalist(id, valeurs) { return el('datalist', { id: id }, valeurs.map(function (v) { return el('option', { value: v }) })) }
    function pastille(texte, ton, ico) { return el('span', { class: 'sf-pastille sf-pastille--' + (ton || 'gris') }, [ico ? icone(ico) : null, texte]) }
    // chips(liste, max) : mots affichés en puces. Au-delà de max, une puce « +N » résume le reste
    // (détail au survol grâce à title). slice(0, max) prend les premiers ; concat ajoute la puce « +N ».
    function chips(liste, max) {
        max = max || liste.length
        return el('div', { class: 'sf-chips-l' }, liste.slice(0, max).map(function (b) { return el('span', { class: 'sf-chip', text: b }) })
            .concat(liste.length > max ? [el('span', { class: 'sf-chip sf-chip--plus', text: '+' + (liste.length - max), title: liste.slice(max).join(', ') })] : []))
    }
    // vide(texte, indication) : bloc « rien à afficher », avec une phrase d’aide facultative.
    function vide(texte, indication) {
        return el('div', { class: 'sf-vide' }, [el('p', { text: texte }), indication ? el('p', { class: 'sf-vide__i', text: indication }) : null])
    }

    // ---------- bulles d’aide « ? » ----------
    // aide(cle) : le bouton « ? » et sa bulle, d’après AIDE[cle] (null si la clé n’existe pas).
    // La bulle est déjà dans la page, cachée par le CSS ; le clic ajoute la classe sf-q-wrap--on
    // qui l’affiche. Rien n’est redessiné : un formulaire en cours de saisie reste intact.
    function aide(cle) {
        var a = AIDE[cle]
        if (!a) { return null }
        var bulle = el('span', { class: 'sf-bulle', role: 'tooltip' }, [el('strong', { text: a[0] }), el('span', { text: a[1] })])
        var b = el('button', { type: 'button', class: 'sf-q', 'aria-label': 'Aide : ' + a[0], 'aria-expanded': 'false', text: '?' })
        var w = el('span', { class: 'sf-q-wrap' }, [b, bulle])
        // preventDefault : pas d’envoi de formulaire. stopPropagation : le clic ne remonte pas jusqu’au
        // document, dont l’écouteur (plus bas) refermerait aussitôt la bulle.
        b.addEventListener('click', function (e) {
            e.preventDefault(); e.stopPropagation()
            // Bulle déjà ouverte : on la ferme. Sinon on ferme les autres et on ouvre celle-ci.
            // aria-expanded informe les lecteurs d’écran.
            var ouverte = w.classList.contains('sf-q-wrap--on')
            fermerBulles()
            if (ouverte) { return }
            w.classList.add('sf-q-wrap--on'); b.setAttribute('aria-expanded', 'true')
            // getBoundingClientRect donne la position à l’écran : si la bulle dépasse à droite, on la cale à gauche.
            bulle.classList.remove('sf-bulle--droite')
            if (bulle.getBoundingClientRect().right > window.innerWidth - 12) { bulle.classList.add('sf-bulle--droite') }
        })
        return w
    }
    // Ferme toutes les bulles ouvertes (querySelectorAll renvoie une NodeList, parcourue par forEach).
    function fermerBulles() {
        root.querySelectorAll('.sf-q-wrap--on').forEach(function (w) { w.classList.remove('sf-q-wrap--on'); w.firstChild.setAttribute('aria-expanded', 'false') })
    }
    // Écouteurs posés une seule fois sur tout le document : un clic hors d’une bulle, ou Échap,
    // ferme les bulles. closest() remonte les parents à la recherche du sélecteur.
    document.addEventListener('click', function (e) { if (!(e.target.closest && e.target.closest('.sf-bulle'))) { fermerBulles() } })
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { fermerBulles() } })

    // ---------- blocs de mise en page ----------
    // entete(titre, sous-titre, boutons, cléAide, avant) : l’en-tête de chaque écran.
    // « avant » = lien de retour au-dessus du titre. actions.filter(Boolean) retire les null :
    // s’il ne reste aucun bouton, pas de zone d’actions.
    function entete(titre, sous, actions, aideCle, avant) {
        return el('header', { class: 'sf-head' }, [
            el('div', { class: 'sf-head__t' }, [avant || null, el('h1', { class: 'sf-titre' }, [titre, aideCle ? aide(aideCle) : null]), sous ? el('div', { class: 'sf-sous', text: sous }) : null]),
            actions && actions.filter(Boolean).length ? el('div', { class: 'sf-head__actions' }, actions) : null
        ])
    }
    // section(titre, cléAide, enfants, classe) : bloc avec un titre h2 ; [h2].concat(enfants) met le titre devant.
    function section(titre, aideCle, enfants, classe) {
        return el('section', { class: 'sf-sec' + (classe ? ' ' + classe : '') }, [el('h2', { class: 'sf-h2' }, [titre, aideCle ? aide(aideCle) : null])].concat(enfants || []))
    }
    // bandeau(ton, texte, cléAide, extra) : message pleine largeur ('alerte', 'guide' ou 'info').
    // Les ternaires enchaînés choisissent l’icône selon le ton.
    function bandeau(ton, texte, aideCle, extra) {
        return el('div', { class: 'sf-bandeau sf-bandeau--' + ton, role: ton === 'alerte' ? 'note' : null }, [icone(ton === 'alerte' ? 'warning' : ton === 'guide' ? 'help' : 'archive'), el('div', { class: 'sf-bandeau__t' }, [texte]), aideCle ? aide(aideCle) : null, extra || null])
    }
    // segment(options, actif, action) : boutons dont un seul est enfoncé (ex. « En cours / Closes »).
    // options = [[valeur, libellé], …]. Chaque fonction de clic garde « son » o grâce à la fermeture.
    function segment(options, actif, action) {
        return el('div', { class: 'sf-seg', role: 'group' }, options.map(function (o) {
            return bouton('sf-seg__btn' + (actif === o[0] ? ' sf-seg__btn--on' : ''), o[1], function () { action(o[0]) }, { 'aria-pressed': actif === o[0] ? 'true' : 'false' })
        }))
    }
    // Liste « libellé : valeur » ; les valeurs vides s’affichent « — ».
    function infos(lignes) {
        // Chaque ligne c = [libellé, valeur, format]. 'tel' / 'mail' donnent un lien cliquable
        // (tel:, mailto:) ; pour tel: on ne garde que les chiffres et le +.
        return el('dl', { class: 'sf-dl' }, lignes.map(function (c) {
            var v = c[1]
            var dd = el('dd', { class: v ? 'sf-texte' : 'sf-dl__vide' })
            if (!v) { dd.textContent = '—' }
            else if (c[2] === 'tel') { dd.appendChild(el('a', { href: 'tel:' + v.replace(/[^0-9+]/g, ''), text: v })) }
            else if (c[2] === 'mail') { dd.appendChild(el('a', { href: 'mailto:' + v, text: v })) }
            else if (typeof v === 'string') { dd.textContent = v } else { dd.appendChild(v) }
            return [el('dt', { text: c[0] }), dd]
        // map a produit des paires [dt, dd] ; reduce + concat les mettent à plat : dt, dd, dt, dd…
        }).reduce(function (a, b) { return a.concat(b) }, []))
    }

    // ---------- formulaires ----------
    // def : { cle, label, type: text|date|time|email|tel|textarea|select|chips, options, liste,
    //         aide (clé d’AIDE), indication, obligatoire, large, placeholder, vide, format }
    function champ(def, valeur) {
        // champ(def, valeur) : un champ complet (libellé, bulle d’aide, saisie, indication) selon def.type.
        // Chaque saisie porte data-cle : c’est ainsi que lire() retrouvera sa valeur.
        var id = 'sf-c-' + def.cle, input
        if (def.type === 'textarea') {
            input = el('textarea', { id: id, class: 'sf-input sf-input--zone', rows: def.lignes || 3, 'data-cle': def.cle, placeholder: def.placeholder || null })
        // Liste déroulante. Options = textes simples ou paires [valeur, libellé]. Si la valeur enregistrée
        // n’est plus dans la liste (réglages changés depuis), on l’ajoute pour ne pas la perdre.
        } else if (def.type === 'select') {
            var opts = (def.options || []).map(function (o) { return Array.isArray(o) ? o : [o, o] })
            var v = valeur == null ? '' : String(valeur)
            if (v && !opts.some(function (o) { return String(o[0]) === v })) { opts.push([v, def.format ? def.format(v) : v]) }   // valeur ancienne, absente de la liste
            input = el('select', { id: id, class: 'sf-input', 'data-cle': def.cle }, [el('option', { value: '', text: def.vide || '—' })].concat(opts.map(function (o) { return el('option', { value: String(o[0]), text: o[1] }) })))
        // « chips » : cases à cocher présentées en puces (plusieurs choix). indexOf(c) === -1 signifie
        // « c absent du tableau » : on ajoute ainsi les anciennes valeurs qui ne sont plus proposées.
        } else if (def.type === 'chips') {
            var choisis = valeur || []
            var toutes = (def.options || []).concat(choisis.filter(function (c) { return (def.options || []).indexOf(c) === -1 }))
            input = el('div', { id: id, class: 'sf-chips', 'data-cle': def.cle, 'data-type': 'chips', role: 'group', 'aria-label': def.label }, toutes.map(function (o) {
                return el('label', { class: 'sf-chip-choix' }, [el('input', { type: 'checkbox', value: o, checked: choisis.indexOf(o) !== -1 ? true : null }), el('span', { text: o })])
            }))
        // Sinon : <input> classique (text, date, time, email, tel…). list relie l’input à un <datalist>.
        } else {
            input = el('input', { id: id, class: 'sf-input', type: def.type || 'text', 'data-cle': def.cle, list: def.liste || null, autocomplete: 'off', placeholder: def.placeholder || null })
        }
        // On remplit la valeur (les puces, elles, sont déjà cochées plus haut).
        if (def.type !== 'chips') { input.value = valeur == null ? '' : String(valeur) }
        // On emballe le tout. « for » relie le libellé à la saisie : un clic sur le libellé place le curseur.
        // Un « * » signale un champ obligatoire (le serveur le vérifie aussi).
        return el('div', { class: 'sf-champ' + (def.large ? ' sf-champ--large' : '') }, [
            el('div', { class: 'sf-lbl-l' }, [el(def.type === 'chips' ? 'span' : 'label', { class: 'sf-lbl', for: def.type === 'chips' ? null : id, text: def.label + (def.obligatoire ? ' *' : '') }), def.aide ? aide(def.aide) : null]),
            input,
            def.indication ? el('div', { class: 'sf-indic', text: def.indication }) : null
        ])
    }
    // Lit tous les champs [data-cle] d’un bloc → { cle: valeur }.
    function lire(bloc) {
        // dataset.cle lit l’attribut data-cle. Puces : Array.prototype.map.call applique map à une
        // NodeList (qui n’a pas de map) pour récupérer les valeurs cochées. Case à cocher : true/false.
        var out = {}
        bloc.querySelectorAll('[data-cle]').forEach(function (i) {
            if (i.dataset.type === 'chips') { out[i.dataset.cle] = Array.prototype.map.call(i.querySelectorAll('input:checked'), function (c) { return c.value }) }
            else { out[i.dataset.cle] = i.type === 'checkbox' ? i.checked : i.value }
        })
        return out
    }
    // Groupes de champs : [[titre, [defs]], …]
    // Chaque groupe devient un <fieldset> avec sa légende ; valeurs[d.cle] pré-remplit chaque champ.
    function fieldsets(groupes, valeurs) {
        return groupes.map(function (g) {
            return el('fieldset', { class: 'sf-fieldset' }, [el('legend', { class: 'sf-legend', text: g[0] }), el('div', { class: 'sf-grille' }, g[1].map(function (d) { return champ(d, valeurs[d.cle]) }))])
        })
    }
    // actionsForm(annuler, libellé, envoyer) : boutons Annuler / Enregistrer d’un formulaire.
    // envoyer() doit renvoyer une promesse : on désactive le bouton pendant l’envoi (pas de double clic)
    // et, en cas d’échec (.catch), on le réactive et on affiche l’erreur.
    // e.currentTarget = le bouton lui-même, même si le clic a touché son icône.
    function actionsForm(annuler, libelle, envoyer) {
        return el('div', { class: 'sf-actions' }, [
            annuler ? bouton('sf-btn2', 'Annuler', annuler) : null,
            bouton('sf-btn', libelle, function (e) {
                var b = e.currentTarget
                b.disabled = true
                envoyer().catch(function (err) { b.disabled = false; erreur(err) })
            })
        ])
    }

    // =====================================================================
    // Chargement et navigation
    // =====================================================================
    // Listes de choix des formulaires (fixées dans lib/Service/Reglages.php).
    // Chaque fonction de chargement appelle l’API puis range la réponse dans S. Elle renvoie la
    // promesse, ce qui permet d’enchaîner .then(render).
    // chargerJournal(id) : journal d’une famille ; sans id, journal de toutes les familles (direction).
    function chargerReglages() { return api('GET', '/reglages').then(function (r) { S.reglages = r }) }
    function chargerAccueil() { return api('GET', '/accueil').then(function (r) { S.accueil = r }) }
    function chargerListe() { return api('GET', '/familles').then(function (r) { S.liste = r.familles }) }
    function chargerFiche(id) { return api('GET', '/familles/' + id).then(function (r) { S.fiche = r; S.ficheId = id }) }
    function chargerJournal(familleId) { return api('GET', '/journal' + (familleId ? '?famille=' + familleId : '')).then(function (r) { S.journal = r }) }

    // aller('familles') change l’adresse après le #. Le navigateur déclenche alors l’événement
    // hashchange, qui appelle route(). Si l’adresse est déjà la bonne, aucun événement :
    // on appelle route() nous-mêmes.
    function aller(hash) { if (location.hash === '#' + hash) { route() } else { location.hash = hash } }
    // Aller sur une fiche ET ouvrir directement un formulaire (ex. « ajouter un membre »).
    function allerEditer(hash, edition) { S.editionApres = edition; aller(hash) }

    // route() : le ROUTEUR maison. Il lit location.hash (« #famille/12/suivi »), choisit l’écran,
    // charge les données utiles, puis appelle render(). Appelé au démarrage et à chaque hashchange
    // (clic sur un lien #…, boutons Précédent / Suivant du navigateur).
    function route() {
        // On retire le # (et un éventuel /), puis split('/') découpe : ['famille', '12', 'suivi'].
        var h = (location.hash || '').replace(/^#\/?/, '').split('/')
        var v = h[0] || 'accueil'
        // Le formulaire « à ouvrir après » (allerEditer) devient le formulaire ouvert ; le reste est remis à zéro.
        S.edition = S.editionApres; S.editionApres = null; S.cloture = false
        fermerBulles()
        // #liste : ancienne adresse, gardée par compatibilité. Écrans direction : les autres sont renvoyés
        // à l’accueil. Simple confort : le serveur refuse de toute façon de leur fournir ces données.
        if (v === 'liste') { v = 'familles' }
        if (['nouvelle', 'journal'].indexOf(v) !== -1 && !direction) { v = 'accueil' }
        // Fiche d’une famille : parseInt('12', 10) → 12. Onglet inconnu (ou journal hors direction) → synthèse.
        if (v === 'famille' && h[1]) {
            var id = parseInt(h[1], 10)
            var onglet = ONGLETS.indexOf(h[2]) !== -1 ? h[2] : 'synthese'
            if (onglet === 'journal' && !direction) { onglet = 'synthese' }
            // Autre famille : on remet les filtres du Suivi à zéro. Même famille déjà chargée :
            // Promise.resolve() = promesse « déjà tenue », donc pas d’appel serveur en changeant d’onglet.
            var autre = S.ficheId !== id
            S.vue = 'fiche'; S.onglet = onglet
            if (autre) { S.filtreSuivi = { type: '', membre: '' }; S.voirFaites = false }
            var p = (!autre && S.fiche) ? Promise.resolve() : chargerFiche(id)
            // Chaîne de promesses : charger le journal si besoin, puis dessiner. En cas d’erreur
            // (fiche supprimée, accès retiré…), on affiche le message et on revient à la liste.
            return p.then(function () { return onglet === 'journal' ? chargerJournal(id) : null }).then(render).catch(function (e) { erreur(e); aller('familles') })
        }
        // Autres écrans : on oublie la fiche ; une adresse inconnue mène à l’accueil.
        S.fiche = null; S.ficheId = null
        S.vue = ['accueil', 'familles', 'nouvelle', 'journal', 'guide'].indexOf(v) !== -1 ? v : 'accueil'
        // localStorage garde une petite info dans le navigateur (« guide vu »). Toujours dans un
        // try/catch : il peut être bloqué (navigation privée…), et cela ne doit pas casser l’appli.
        if (S.vue === 'guide') { try { localStorage.setItem('sf-guide-vu', '1') } catch (e) { /* stockage indisponible : sans importance */ } }
        // Table « écran → fonction de chargement ». Sans chargement (nouvelle, guide) : Promise.resolve().
        var charger = { accueil: chargerAccueil, familles: chargerListe, journal: function () { return chargerJournal(null) } }[S.vue]
        return (charger ? charger() : Promise.resolve()).then(render).catch(erreur)
    }
    // On branche le routeur : chaque changement du # dans l’adresse appelle route().
    window.addEventListener('hashchange', route)
    // rechargerFiche() : après un enregistrement, on ferme les formulaires, on relit la fiche
    // sur le serveur (la source de vérité) et on redessine.
    function rechargerFiche() { S.edition = null; S.cloture = false; return chargerFiche(S.ficheId).then(render) }

    // =====================================================================
    // DESSIN
    // =====================================================================
    // derniereVue : l’écran précédent, pour savoir si render() redessine le même écran
    // (on garde le défilement) ou un nouveau (on remonte en haut).
    var derniereVue = ''
    // render() : redessine TOUT l’écran à partir de S. Simple et fiable : on vide la racine et on
    // reconstruit. Ce faisant on perdrait focus, défilement et toasts : on les sauve avant, on les remet après.
    function render() {
        // Array.prototype.slice.call transforme la NodeList en vrai tableau. focus : id du champ actif
        // (pour continuer à taper dans une recherche après le redessin).
        var toasts = Array.prototype.slice.call(root.querySelectorAll('.sf-toast'))
        var focus = document.activeElement && document.activeElement.id
        var cle = S.vue + '|' + S.ficheId + '|' + S.onglet
        var defilement = cle === derniereVue ? root.scrollTop : 0
        derniereVue = cle
        // innerHTML = '' vide la racine (aucune saisie n’est injectée ici, donc sans risque).
        root.innerHTML = ''
        // On choisit la fonction qui dessine l’écran courant, puis on assemble : menu à gauche, page à droite.
        var vue = { accueil: vueAccueil, familles: vueFamilles, nouvelle: vueNouvelle, fiche: vueFiche, journal: vueJournal, guide: vueGuide }[S.vue] || vueAccueil
        root.appendChild(el('div', { class: 'sf-shell' }, [navigation(), el('main', { class: 'sf-main' }, [el('div', { class: 'sf-page' }, [vue()])])]))
        // On remet les toasts, le défilement et le focus.
        toasts.forEach(function (t) { root.appendChild(t) })
        root.scrollTop = defilement
        if (focus && document.getElementById(focus)) { document.getElementById(focus).focus() }
    }

    // navigation() : le menu latéral. actif = l’entrée surlignée (une fiche ou « nouvelle » surlignent
    // « Familles »). aria-current='page' la signale aux lecteurs d’écran. « Journal des accès » n’est
    // montré qu’à la direction ; masquer ne protège rien, le serveur refuse le journal aux autres.
    function navigation() {
        var actif = { fiche: 'familles', nouvelle: 'familles' }[S.vue] || S.vue
        var lien = function (it) {
            return el('a', { class: 'sf-nav__l' + (actif === it[0] ? ' sf-nav__l--on' : ''), href: '#' + it[0], 'aria-current': actif === it[0] ? 'page' : null }, [icone(it[2]), el('span', { text: it[1] })])
        }
        var items = [['accueil', 'Accueil', 'home'], ['familles', 'Familles', 'people']]
        if (direction) { items.push(['journal', 'Journal des accès', 'history']) }
        return el('nav', { class: 'sf-nav', 'aria-label': 'Menu de l’appli Familles' }, [
            el('div', { class: 'sf-nav__groupe' }, items.map(lien)),
            el('div', { class: 'sf-nav__groupe sf-nav__bas' }, [
                lien(['guide', 'Guide d’utilisation', 'help']),
                el('div', { class: 'sf-nav__profil' }, [el('span', { class: 'sf-avatar sf-avatar--p', text: initiale(monNom) }), el('div', {}, [el('div', { class: 'sf-nav__nom', text: monNom }), el('div', { class: 'sf-nav__role', text: direction ? 'Direction : voit toutes les familles' : 'Voit les familles confiées' })])])
            ])
        ])
    }

    // =====================================================================
    // ACCUEIL
    // =====================================================================
    // guideVu() : le guide a-t-il été ouvert ? Si localStorage échoue, on répond « oui »
    // pour ne pas afficher le bandeau à chaque fois.
    function guideVu() { try { return localStorage.getItem('sf-guide-vu') === '1' } catch (e) { return true } }

    // vueAccueil() : l’écran d’accueil. S.accueil (chargerAccueil) contient les chiffres,
    // mes actions, mes familles et, pour la direction, les points d’attention.
    function vueAccueil() {
        var A = S.accueil, c = A.chiffres, w = el('div')
        // Salutation selon l’heure, date du jour en toutes lettres. « Nouvelle famille » : direction seulement.
        var heure = new Date().getHours()
        w.appendChild(entete((heure >= 18 ? 'Bonsoir ' : 'Bonjour ') + prenom(monNom), capitale(dateLongue(aujourdhui())),
            [direction ? bouton('sf-btn', 'Nouvelle famille', function () { aller('nouvelle') }, { ico: 'plus' }) : null], 'accueil'))

        // Bandeau « Première visite ? » tant que le guide n’a pas été vu. « Masquer » le note dans
        // localStorage et retire le bandeau sans tout redessiner (b.remove()).
        if (!guideVu()) {
            var b = bandeau('guide', [el('strong', { text: 'Première visite ? ' }), 'Le guide présente l’appli en deux minutes. Et partout, les petits « ? » expliquent chaque élément.'], null,
                el('div', { class: 'sf-bandeau__a' }, [el('a', { class: 'sf-btn2 sf-btn2--petit', href: '#guide', text: 'Lire le guide' }), bouton('sf-lien', 'Masquer', function () { try { localStorage.setItem('sf-guide-vu', '1') } catch (e) { /* rien */ } b.remove() })]))
            w.appendChild(b)
        }

        // Tuiles de chiffres : [nombre, libellé, ton, lien]. Les ternaires accordent singulier / pluriel.
        var tuiles = direction
            ? [[c.actives, 'familles accompagnées', '', 'familles'], [c.actionsRetard, c.actionsRetard > 1 ? 'de mes actions en retard' : 'action en retard (à moi)', c.actionsRetard ? 'danger' : '', null]]
            : [[c.actives, c.actives > 1 ? 'familles suivies' : 'famille suivie', '', 'familles'], [c.actionsRetard, c.actionsRetard > 1 ? 'actions en retard' : 'action en retard', c.actionsRetard ? 'danger' : '', null]]
        w.appendChild(el('div', { class: 'sf-tuiles' }, tuiles.map(function (t) {
            return el(t[3] ? 'a' : 'div', { class: 'sf-tuile' + (t[2] ? ' sf-tuile--' + t[2] : ''), href: t[3] ? '#' + t[3] : null }, [el('div', { class: 'sf-tuile__n', text: String(t[0]) }), el('div', { class: 'sf-tuile__l', text: t[1] })])
        })))

        // Deux colonnes : à gauche mes actions ; à droite les points d’attention (direction)
        // ou mes familles (intervenants).
        var gauche = el('div', { class: 'sf-col' }, [blocMesActions(A.mesActions)])
        var droite = el('div', { class: 'sf-col' })
        if (direction) { droite.appendChild(blocAttention(A)) }
        else {
            droite.appendChild(blocFamilles('Mes familles', null, A.mesFamilles, 'Aucune famille ne vous est confiée pour l’instant. La direction vous donnera accès aux familles que vous suivez : vous recevrez une notification.', function (f) {
                return f.prochaine ? 'Prochaine étape : ' + f.prochaine.titre + (f.prochaine.echeance ? ' (' + dateFr(f.prochaine.echeance) + ')' : '') : (f.dernierSuivi ? 'Dernier contact ' + relatif(f.dernierSuivi) : 'Pas encore d’intervention')
            }))
        }
        w.appendChild(el('div', { class: 'sf-2col' }, [gauche, droite]))
        return w
    }

    // blocMesActions(liste) : mes actions regroupées par échéance. Le serveur a déjà calculé a.quand
    // (retard, aujourdhui, semaine, plus_tard) : on filtre la liste pour chaque groupe, on saute les vides.
    // Après un clic sur le rond, apres() recharge l’accueil et redessine.
    function blocMesActions(liste) {
        var s = section('Mes actions et rendez-vous', 'mesActions')
        if (!liste.length) {
            s.appendChild(vide('Rien de prévu pour vous.', 'Les actions et rendez-vous se créent dans l’onglet « À faire » de chaque fiche famille.'))
            return s
        }
        [['retard', 'En retard'], ['aujourdhui', 'Aujourd’hui'], ['semaine', 'Dans les 7 jours'], ['plus_tard', 'Plus tard ou sans date']].forEach(function (g) {
            var items = liste.filter(function (a) { return a.quand === g[0] })
            if (!items.length) { return }
            s.appendChild(el('div', { class: 'sf-groupe' + (g[0] === 'retard' ? ' sf-groupe--retard' : ''), text: g[1] + ' · ' + items.length }))
            items.forEach(function (a) { s.appendChild(ligneAction(a, { famille: true, apres: function () { return chargerAccueil().then(render) } })) })
        })
        return s
    }

    // Liste de familles cliquables (accueil). detail(f) = la petite ligne grise sous le nom.
    function blocFamilles(titre, aideCle, liste, texteVide, detail, ton, onglet) {
        var s = section(titre, aideCle)
        if (!liste.length) { s.appendChild(vide(texteVide)); return s }
        liste.forEach(function (f) {
            s.appendChild(el('a', { class: 'sf-fam', href: '#famille/' + f.id + (onglet ? '/' + onglet : '') }, [
                el('span', { class: 'sf-avatar', text: initiale(f.nom) }),
                el('div', { class: 'sf-fam__c' }, [el('div', { class: 'sf-fam__n', text: f.nom + (f.commune ? ' · ' + f.commune : '') }), el('div', { class: 'sf-fam__d' + (ton ? ' sf-ton-' + ton : ''), text: detail(f) })])
            ]))
        })
        return s
    }

    // blocAttention(A) : pour la direction, les familles à régler. Groupe = [familles, titre, onglet à
    // ouvrir, infobulle] ; filter retire les groupes vides. Chaque famille = lien vers le bon onglet.
    function blocAttention(A) {
        var s = section('Points d’attention', 'attention')
        var groupes = [
            [A.sansEquipe, 'Sans intervenant', 'equipe', 'Seule la direction y a accès.'],
            [A.sansReferent, 'Sans référent·e', 'equipe', 'Personne ne coordonne l’accompagnement.'],
            [A.sansConsentement, 'Consentement non noté', 'synthese', 'À recueillir auprès de la famille.']
        ].filter(function (g) { return g[0].length })
        if (!groupes.length) { s.appendChild(vide('Rien à signaler : chaque famille a son équipe, son référent·e et son consentement.')); return s }
        groupes.forEach(function (g) {
            s.appendChild(el('div', { class: 'sf-groupe', text: g[1] + ' · ' + g[0].length }))
            s.appendChild(el('div', { class: 'sf-liens-fam' }, g[0].map(function (f) { return el('a', { class: 'sf-chip sf-chip--lien', href: '#famille/' + f.id + '/' + g[2], text: f.nom, title: g[3] }) })))
        })
        return s
    }

    // Une action ou un rendez-vous, avec son rond à cocher.
    // o : { famille: afficher le nom de la famille, responsable: afficher la personne, apres: fonction de rechargement, outils: [boutons] }
    function ligneAction(a, o) {
        // En retard : pas faite et échéance passée (simple comparaison de textes ISO).
        var retard = !a.fait && a.echeance && a.echeance < aujourdhui()
        // Le rond à cocher ; aria-pressed et aria-label décrivent son état aux lecteurs d’écran.
        var coche = el('button', { type: 'button', class: 'sf-coche-rond' + (a.fait ? ' sf-coche-rond--on' : ''), 'aria-pressed': a.fait ? 'true' : 'false', 'aria-label': a.fait ? 'Remettre à faire : ' + a.titre : 'Marquer comme fait : ' + a.titre, title: a.fait ? 'Remettre à faire' : 'C’est fait' }, [icone('check')])
        // Clic : on inverse « fait » côté serveur (!a.fait), on prévient, puis o.apres() recharge.
        // Le rond est désactivé pendant l’appel pour éviter un double clic.
        coche.addEventListener('click', function () {
            coche.disabled = true
            api('PUT', '/actions/' + a.id, { fait: !a.fait }).then(function () { toast(a.fait ? 'Remise à faire' : 'C’est noté : fait'); return o.apres() }).catch(function (e) { coche.disabled = false; erreur(e) })
        })
        // meta : petites infos sous le titre (date, famille, responsable, « fait le »), si elles existent.
        var meta = []
        if (a.echeance) {
            meta.push(el('span', { class: 'sf-meta' + (retard ? ' sf-meta--retard' : '') }, [icone(a.genre === 'rdv' ? 'event' : 'flag'),
                (a.genre === 'rdv' ? 'Rendez-vous le ' : 'Pour le ') + dateFr(a.echeance) + (a.heure ? ' à ' + heureFr(a.heure) : '') + (retard ? ' · en retard' : '')]))
        }
        if (o.famille) { meta.push(el('a', { class: 'sf-meta sf-meta--lien', href: '#famille/' + a.familleId + '/actions', text: a.famille })) }
        if (o.responsable) { meta.push(el('span', { class: 'sf-meta', text: a.responsableNom ? (a.responsable === moi ? 'Vous' : a.responsableNom) : 'Pas de responsable' })) }
        if (a.fait && a.faitLe) { meta.push(el('span', { class: 'sf-meta', text: 'Fait le ' + quand(a.faitLe) })) }
        return el('div', { class: 'sf-action' + (a.fait ? ' sf-action--faite' : '') + (retard ? ' sf-action--retard' : '') }, [
            coche,
            el('div', { class: 'sf-action__c' }, [el('div', { class: 'sf-action__t', text: a.titre }), meta.length ? el('div', { class: 'sf-action__m' }, meta) : null]),
            o.outils ? el('div', { class: 'sf-action__o' }, o.outils) : null
        ])
    }

    // =====================================================================
    // LISTE DES FAMILLES
    // =====================================================================
    // vueFamilles() : la liste des familles, avec recherche, filtres et tri, le tout dans le navigateur.
    // Le serveur n’a envoyé QUE les familles que la personne a le droit de voir.
    // F = raccourci vers S.filtres : chaque changement modifie F puis appelle render().
    function vueFamilles() {
        var F = S.filtres, R = S.reglages, w = el('div')
        var familles = S.liste || []
        w.appendChild(entete('Familles', direction ? 'Toutes les familles accompagnées par l’association.' : 'Les familles qui vous sont confiées.',
            [direction ? bouton('sf-btn', 'Nouvelle famille', function () { aller('nouvelle') }, { ico: 'plus' }) : null]))

        // Aucune famille du tout : message adapté au rôle, et on s’arrête là.
        if (!familles.length) {
            w.appendChild(el('div', { class: 'sf-sec' }, [vide(direction ? 'Aucune famille pour l’instant.' : 'Aucune famille ne vous est encore confiée.',
                direction ? 'Créez une première fiche avec « Nouvelle famille » : le nom suffit pour commencer.' : 'La direction vous donnera accès aux familles que vous suivez ; vous recevrez une notification.')]))
            return w
        }

        // Barre d’outils : recherche (événement input = à chaque frappe) et « En cours / Closes » avec compteurs.
        // L’id fixe sf-recherche permet à render() de remettre le focus dans le champ après le redessin.
        var nbActives = familles.filter(function (f) { return f.statut === 'actif' }).length
        var recherche = el('input', { id: 'sf-recherche', class: 'sf-input sf-input--recherche', type: 'search', value: F.q, 'aria-label': 'Rechercher une famille', placeholder: 'Rechercher un nom, une commune' + (direction ? ', un intervenant' : '') + '…' })
        recherche.addEventListener('input', function () { F.q = recherche.value; render() })
        w.appendChild(el('div', { class: 'sf-outils' }, [recherche, segment([['actif', 'En cours · ' + nbActives], ['clos', 'Closes · ' + (familles.length - nbActives)]], F.statut, function (v) { F.statut = v; render() })]))

        // filtre(id, cle, libellé, options) : liste déroulante liée à F[cle]. Fonction rangée dans une
        // variable (expression de fonction), utile seulement ici.
        var filtre = function (id, cle, libelleVide, options) {
            var s = el('select', { id: id, class: 'sf-input sf-input--filtre' + (F[cle] ? ' sf-input--actif' : ''), 'aria-label': libelleVide }, [el('option', { value: '', text: libelleVide })].concat(options.map(function (o) {
                o = Array.isArray(o) ? o : [o, o]
                return el('option', { value: o[0], text: o[1] })
            })))
            s.value = F[cle]
            s.addEventListener('change', function () { F[cle] = s.value; render() })
            return s
        }
        // Référents présents dans la liste : un objet sert de dictionnaire uid → nom (donc sans doublon).
        var referents = {}
        familles.forEach(function (f) { if (f.referent) { referents[f.referent] = f.referentNom } })
        var filtresActifs = F.territoire || F.besoin || F.referent || F.q
        // La rangée de filtres. Filtre référent : direction seulement. « Effacer les filtres » n’apparaît
        // que si un filtre est actif ; Object.assign remet plusieurs clés de F à zéro d’un coup.
        w.appendChild(el('div', { class: 'sf-filtres' }, [
            filtre('sf-f-terr', 'territoire', 'Tous les territoires', R.territoires),
            filtre('sf-f-besoin', 'besoin', 'Tous les besoins', R.besoins),
            direction ? filtre('sf-f-ref', 'referent', 'Tous les référents', Object.keys(referents).map(function (k) { return [k, referents[k]] }).concat([['-', 'Sans référent·e']])) : null,
            el('span', { class: 'sf-filtres__sep' }),
            filtre('sf-f-tri', 'tri', 'Trier par nom', [['contact', 'Trier par dernier contact'], ['prochaine', 'Trier par prochaine étape']]),
            filtresActifs ? bouton('sf-lien', 'Effacer les filtres', function () { Object.assign(F, { q: '', territoire: '', besoin: '', referent: '' }); render() }) : null
        ]))

        // On applique les filtres : Array.prototype.filter garde les familles pour lesquelles la fonction
        // renvoie true. Recherche : nom, commune, référent et équipe collés en un texte normalisé ;
        // indexOf(q) === -1 veut dire « pas trouvé ».
        var q = norm(F.q)
        var visibles = familles.filter(function (f) {
            if (f.statut !== F.statut) { return false }
            if (F.territoire && f.territoire !== F.territoire) { return false }
            if (F.besoin && f.besoins.indexOf(F.besoin) === -1) { return false }
            // Référent : '-' = familles SANS référent·e ; sinon, référent·e égal·e à celui choisi.
            if (F.referent === '-' ? f.referent : (F.referent && f.referent !== F.referent)) { return false }
            if (q && norm([f.nom, f.commune, f.referentNom].concat((f.equipe || []).map(function (e) { return e.nom })).join(' ')).indexOf(q) === -1) { return false }
            return true
        })
        // Tri : sort avec une fonction de comparaison ; localeCompare compare deux textes. Valeur absente
        // → '0' (en tête) ou '9999' (en fin). Sans tri choisi, on garde l’ordre reçu du serveur (par nom).
        if (F.tri === 'contact') { visibles.sort(function (a, b) { return (a.dernierSuivi || '0').localeCompare(b.dernierSuivi || '0') }) }
        if (F.tri === 'prochaine') { visibles.sort(function (a, b) { return ((a.prochaine && a.prochaine.echeance) || '9999').localeCompare((b.prochaine && b.prochaine.echeance) || '9999') }) }

        // Compteur de résultats, puis message si rien ne correspond.
        w.appendChild(el('p', { class: 'sf-compte', text: pluriel(visibles.length, 'famille') + (filtresActifs ? ' (filtrées)' : '') }))
        if (!visibles.length) { w.appendChild(el('div', { class: 'sf-sec' }, [vide('Aucune famille ne correspond.', 'Changez la recherche ou les filtres.')])); return w }

        // Le tableau : une ligne d’en-tête, puis une ligne-lien par famille. data-l = nom de la colonne,
        // repris par le CSS (attr(data-l)) quand le tableau s’affiche en cartes sur petit écran.
        var tab = el('div', { class: 'sf-tab', role: 'list' }, [el('div', { class: 'sf-tab__tete', 'aria-hidden': 'true' }, ['Famille', 'Besoins', 'Référent·e', 'Dernier contact', 'Prochaine étape'].map(function (t) { return el('span', { text: t }) }))])
        visibles.forEach(function (f) {
            // signes : pastilles d’alerte sous le nom (sans intervenant, consentement manquant).
            var signes = []
            if (direction && f.statut === 'actif' && !f.equipe.length) { signes.push(pastille('Sans intervenant', 'alerte')) }
            if (f.sansConsentement) { signes.push(pastille('Consentement ?', 'alerte')) }
            var p = f.prochaine
            tab.appendChild(el('a', { class: 'sf-tab__l', href: '#famille/' + f.id, role: 'listitem' }, [
                el('div', { class: 'sf-tab__nom' }, [el('span', { class: 'sf-avatar', text: initiale(f.nom) }), el('div', { class: 'sf-tab__id' }, [
                    el('div', { class: 'sf-tab__n', text: f.nom }),
                    el('div', { class: 'sf-tab__s' }, [[f.commune, pluriel(f.nbMembres, 'membre')].filter(Boolean).join(' · ')].concat(signes))
                ])]),
                el('div', { class: 'sf-tab__c', 'data-l': 'Besoins' }, [f.besoins.length ? chips(f.besoins, 2) : el('span', { class: 'sf-dl__vide', text: '—' })]),
                el('div', { class: 'sf-tab__c', 'data-l': 'Référent·e', text: f.referentNom || '—' }),
                el('div', { class: 'sf-tab__c', 'data-l': 'Dernier contact', text: f.statut === 'clos' ? 'Close' + (f.dateFin ? ' le ' + dateFr(f.dateFin) : '') : (f.dernierSuivi ? relatif(f.dernierSuivi) : 'aucun') }),
                el('div', { class: 'sf-tab__c', 'data-l': 'Prochaine étape' }, [p ? el('span', {}, [el('span', { class: 'sf-tab__p', text: p.titre }), p.echeance ? el('span', { class: 'sf-tab__s' + (p.echeance < aujourdhui() ? ' sf-ton-danger' : ''), text: (p.genre === 'rdv' ? 'RDV ' : '') + dateFr(p.echeance) }) : null]) : el('span', { class: 'sf-dl__vide', text: '—' })])
            ]))
        })
        w.appendChild(tab)
        return w
    }

    // =====================================================================
    // NOUVELLE FAMILLE / MODIFIER LA FICHE
    // =====================================================================
    // groupesFamille() : description des champs d’une famille, en groupes [titre, [définitions]].
    // Les listes de choix viennent des réglages. Même description en création et en modification.
    function groupesFamille() {
        var R = S.reglages
        return [
            ['Identité et coordonnées', [
                { cle: 'nom', label: 'Nom de la famille', obligatoire: true, placeholder: 'Ex. Famille Martin' },
                { cle: 'telephone', label: 'Téléphone', type: 'tel' },
                { cle: 'email', label: 'E-mail', type: 'email' },
                { cle: 'adresse', label: 'Adresse', placeholder: 'N° et rue' },
                { cle: 'commune', label: 'Commune' },
                { cle: 'territoire', label: 'Territoire', type: 'select', options: R.territoires, aide: 'territoire', vide: 'Choisir…' }
            ]],
            ['Accompagnement', [
                { cle: 'dateEntree', label: 'Début de l’accompagnement', type: 'date', aide: 'debut' },
                { cle: 'orientation', label: 'Orientée par', type: 'select', options: R.orientations, aide: 'orientation', vide: 'Choisir…' },
                { cle: 'besoins', label: 'Besoins', type: 'chips', options: R.besoins, aide: 'besoins', large: true, indication: 'Cliquez pour sélectionner, plusieurs choix possibles.' },
                { cle: 'situation', label: 'Situation', type: 'textarea', lignes: 5, large: true, aide: 'situation', placeholder: 'Composition de la famille, contexte, ce qui l’amène…' }
            ]],
            ['Consentement (RGPD)', [
                { cle: 'consentement', label: 'Consentement recueilli le', type: 'date', aide: 'consentement', indication: 'Laissez vide tant que la famille n’a pas donné son accord.' }
            ]]
        ]
    }
    // formFamille(valeurs, libellé, enregistrer, annuler) : le formulaire complet. Au clic,
    // lire(bloc) rassemble les saisies ; enregistrer(…) doit renvoyer la promesse de l’appel API.
    function formFamille(f, libelle, enregistrer, annuler) {
        var bloc = el('div', { class: 'sf-form' }, fieldsets(groupesFamille(), f))
        bloc.appendChild(actionsForm(annuler, libelle, function () { return enregistrer(lire(bloc)) }))
        return bloc
    }
    // lienRetour : le lien « ← Familles » au-dessus du titre.
    function lienRetour(hash, texte) { return el('a', { class: 'sf-retour', href: '#' + hash }, [icone('back'), texte]) }

    // vueNouvelle() : création d’une famille (direction ; le serveur vérifie). Date d’entrée = aujourd’hui.
    // Le POST renvoie la famille créée : on ouvre aussitôt sa fiche.
    function vueNouvelle() {
        return el('div', {}, [
            entete('Nouvelle famille', 'Seul le nom est obligatoire : le reste peut être complété plus tard.', [], null, lienRetour('familles', 'Familles')),
            formFamille({ dateEntree: aujourdhui() }, 'Créer la fiche', function (v) {
                return api('POST', '/familles', v).then(function (f) { toast('Fiche créée'); aller('famille/' + f.id) })
            }, function () { aller('familles') })
        ])
    }

    // =====================================================================
    // FICHE D’UNE FAMILLE
    // =====================================================================
    // vueFiche() : la fiche d’une famille. F = réponse du serveur (famille, membres, suivis, actions,
    // équipe, droits…). d = F.droits : ce que la personne peut faire, CALCULÉ PAR LE SERVEUR.
    // On s’en sert pour afficher ou masquer des boutons, mais masquer ne protège rien :
    // lib/Service/FamillesService.php revérifie les droits à chaque requête.
    function vueFiche() {
        var F = S.fiche, f = F.famille, d = F.droits, w = el('div')
        // Mode « Modifier la fiche » : seulement le formulaire, pré-rempli avec f, et on s’arrête (return w).
        if (S.edition && S.edition.type === 'famille') {
            w.appendChild(entete('Modifier la fiche', f.nom, [], null, lienRetour('famille/' + f.id, f.nom)))
            w.appendChild(formFamille(f, 'Enregistrer', function (v) {
                return api('PUT', '/familles/' + f.id, v).then(function () { toast('Fiche enregistrée'); return rechargerFiche() })
            }, function () { S.edition = null; render() }))
            return w
        }

        // Boutons de l’en-tête ; null = non affiché (el() l’ignore). Modifier : si d.modifier ;
        // Clore / Rouvrir : direction, selon le statut.
        var actions = [
            d.modifier ? bouton('sf-btn2', 'Modifier', function () { S.edition = { type: 'famille' }; render() }, { ico: 'edit' }) : null,
            bouton('sf-btn2', 'Imprimer', imprimerFiche, { ico: 'print', title: 'Imprimer ou enregistrer en PDF (noté au journal)' }),
            d.direction && f.statut === 'actif' ? bouton('sf-btn2', 'Clore', function () { S.cloture = !S.cloture; render() }, { ico: 'archive', 'aria-expanded': S.cloture ? 'true' : 'false' }) : null,
            d.direction && f.statut === 'clos' ? bouton('sf-btn2', 'Rouvrir', function () {
                api('PUT', '/familles/' + f.id + '/statut', { statut: 'actif' }).then(function () { toast('Accompagnement rouvert'); return rechargerFiche() }).catch(erreur)
            }) : null
        ]
        // Sous-titre « commune · territoire » (filter(Boolean) retire les vides), puis l’en-tête.
        var sous = [f.commune, f.territoire].filter(Boolean).join(' · ')
        w.appendChild(entete([f.nom, f.statut === 'clos' ? pastille('Close', 'gris') : null], sous || null, actions, null, lienRetour('familles', 'Familles')))

        // Sous l’en-tête : panneau de clôture s’il est ouvert, bandeau « close » ou alerte
        // « consentement manquant », puis les 4 repères.
        if (S.cloture) { w.appendChild(panneauCloture(f)) }
        if (f.statut === 'clos') {
            w.appendChild(bandeau('info', 'Accompagnement terminé' + (f.dateFin ? ' le ' + dateFr(f.dateFin) : '') + (f.motifFin ? ' · ' + f.motifFin : '') + '.', 'cloture'))
        } else if (!f.consentement) {
            w.appendChild(bandeau('alerte', 'Consentement de la famille non renseigné.' + (d.modifier ? ' Notez sa date avec « Modifier » dès qu’il est recueilli.' : ''), 'consentement'))
        }
        w.appendChild(faits(F))

        // Les onglets, certains avec un compteur (o[2]). Ce sont de vrais liens #famille/12/onglet :
        // le routeur fait le reste. Journal : direction seulement.
        var ouvertes = F.actions.filter(function (a) { return !a.fait }).length
        var liste = [['synthese', 'Synthèse'], ['membres', 'Membres', F.membres.length], ['suivi', 'Suivi', F.suivis.length], ['actions', 'À faire', ouvertes], ['equipe', 'Équipe', F.equipe.length]]
        if (d.direction) { liste.push(['journal', 'Journal']) }
        w.appendChild(el('nav', { class: 'sf-onglets', role: 'tablist', 'aria-label': 'Parties de la fiche' }, liste.map(function (o) {
            var on = S.onglet === o[0]
            return el('a', { class: 'sf-onglet' + (on ? ' sf-onglet--on' : ''), href: '#famille/' + f.id + '/' + o[0], role: 'tab', 'aria-selected': on ? 'true' : 'false' }, [o[1], o[2] != null ? el('span', { class: 'sf-nb', text: String(o[2]) }) : null])
        })))
        // On appelle la fonction de l’onglet courant : une table « nom → fonction » évite une suite de if.
        w.appendChild({ synthese: ongletSynthese, membres: ongletMembres, suivi: ongletSuivi, actions: ongletActions, equipe: ongletEquipe, journal: ongletJournal }[S.onglet]())
        return w
    }

    // Les 4 repères en haut de la fiche.
    function faits(F) {
        var f = F.famille
        // Première action non faite de la liste reçue = prochaine étape.
        // Chaque case = [libellé, valeur, cléAide, ton].
        var prochaine = F.actions.filter(function (a) { return !a.fait })[0]
        var cases = [
            ['Référent·e', f.referentNom || 'Non désigné·e', 'referent', !f.referent && f.statut === 'actif' ? 'alerte' : ''],
            ['Accompagnée depuis', f.dateEntree ? dateFr(f.dateEntree) : '—', 'debut', ''],
            ['Dernier contact', F.dernierSuivi ? capitale(relatif(F.dernierSuivi)) : 'Aucun', 'dernierContact', ''],
            ['Prochaine étape', prochaine ? prochaine.titre + (prochaine.echeance ? ' · ' + dateFr(prochaine.echeance) : '') : 'Rien de prévu', 'prochaine', prochaine && prochaine.echeance && prochaine.echeance < aujourdhui() ? 'danger' : '']
        ]
        return el('div', { class: 'sf-faits' }, cases.map(function (c) {
            return el('div', { class: 'sf-fait' }, [el('div', { class: 'sf-fait__l' }, [c[0], aide(c[2])]), el('div', { class: 'sf-fait__v' + (c[3] ? ' sf-ton-' + c[3] : ''), text: c[1] })])
        }))
    }

    // panneauCloture(f) : formulaire « Clore » (date de fin + motif), affiché quand S.cloture est vrai.
    // Le bouton n’est montré qu’à la direction ; le serveur le vérifie aussi.
    function panneauCloture(f) {
        var bloc = el('div', { class: 'sf-form sf-form--encart sf-cloture' }, [
            el('h2', { class: 'sf-h2' }, ['Clore l’accompagnement', aide('cloture')]),
            el('div', { class: 'sf-grille' }, [
                champ({ cle: 'dateFin', label: 'Date de fin', type: 'date' }, aujourdhui()),
                champ({ cle: 'motif', label: 'Motif', type: 'select', options: S.reglages.motifs, vide: 'Choisir…' }, '')
            ]),
            el('p', { class: 'sf-indic', text: 'La fiche passera dans « Closes ». Pensez ensuite à retirer l’accès des intervenants (onglet Équipe).' })
        ])
        bloc.appendChild(actionsForm(function () { S.cloture = false; render() }, 'Clore l’accompagnement', function () {
            var v = lire(bloc)
            return api('PUT', '/familles/' + f.id + '/statut', { statut: 'clos', dateFin: v.dateFin, motif: v.motif }).then(function () { toast('Accompagnement clos'); return rechargerFiche() })
        }))
        return bloc
    }

    // ----- Synthèse -----
    // ongletSynthese() : en haut « Pour bien démarrer » s’il reste des étapes ; puis deux colonnes
    // (situation, objectifs, prochaines étapes | coordonnées, accompagnement) ; enfin, pour la
    // direction, la zone de suppression.
    function ongletSynthese() {
        var F = S.fiche, f = F.famille, d = F.droits
        var w = el('div')
        var demarrage = etapesDemarrage(F)
        if (demarrage) { w.appendChild(demarrage) }

        var situation = section('Situation', 'situation', [f.situation ? el('p', { class: 'sf-texte', text: f.situation }) : vide('Pas encore décrite.', d.modifier ? 'Bouton « Modifier » en haut de la fiche.' : null)])
        var coordonnees = section('Coordonnées', null, [infos([['Téléphone', f.telephone, 'tel'], ['E-mail', f.email, 'mail'], ['Adresse', [f.adresse, f.commune].filter(Boolean).join(', ')]])])
        var accompagnement = section('Accompagnement', null, [
            infos([['Début', f.dateEntree ? dateFr(f.dateEntree) : ''], ['Orientée par', f.orientation], ['Territoire', f.territoire], ['Consentement', f.consentement ? 'Recueilli le ' + dateFr(f.consentement) : '']]),
            el('div', { class: 'sf-lbl-l sf-lbl-l--haut' }, [el('span', { class: 'sf-lbl', text: 'Besoins' }), aide('besoins')]),
            f.besoins.length ? chips(f.besoins) : el('p', { class: 'sf-dl__vide', text: 'Non renseignés' })
        ])
        w.appendChild(el('div', { class: 'sf-2col' }, [
            el('div', { class: 'sf-col' }, [situation, blocObjectifs(), blocProchaines()]),
            el('div', { class: 'sf-col' }, [coordonnees, accompagnement])
        ]))
        if (d.direction) { w.appendChild(zoneSensible(f)) }
        return w
    }

    // « Pour bien démarrer » : disparaît quand tout est fait.
    function etapesDemarrage(F) {
        var f = F.famille, d = F.droits
        // Seulement pour une fiche en cours, et si l’on peut la modifier.
        if (f.statut !== 'actif' || !d.modifier) { return null }
        var id = f.id
        // Étape = [libellé, faite ?, action au clic]. !! transforme une valeur en vrai booléen.
        // Les étapes direction valent null pour les autres : filter(Boolean) les retire.
        var etapes = [
            ['Décrire la situation et les besoins', !!(f.situation || f.besoins.length), function () { S.edition = { type: 'famille' }; render() }],
            ['Ajouter les membres de la famille', F.membres.length > 0, function () { allerEditer('famille/' + id + '/membres', { type: 'membre', id: null }) }],
            d.direction ? ['Ouvrir l’accès aux intervenants', F.equipe.length > 0, function () { aller('famille/' + id + '/equipe') }] : null,
            d.direction ? ['Désigner un·e référent·e', !!f.referent, function () { aller('famille/' + id + '/equipe') }] : null,
            ['Noter la date du consentement', !!f.consentement, function () { S.edition = { type: 'famille' }; render() }],
            ['Fixer un premier objectif', F.objectifs.length > 0, function () { var i = document.getElementById('sf-nouvel-objectif'); if (i) { i.scrollIntoView({ block: 'center' }); i.focus() } }]
        ].filter(Boolean)
        // Tout est fait : le bloc disparaît (return null). La barre de progression est une largeur en %.
        var faites = etapes.filter(function (e) { return e[1] }).length
        if (faites === etapes.length) { return null }
        return el('section', { class: 'sf-demarrage' }, [
            el('div', { class: 'sf-demarrage__tete' }, [el('h2', { class: 'sf-h2', text: 'Pour bien démarrer' }), el('span', { class: 'sf-demarrage__n', text: faites + ' / ' + etapes.length })]),
            el('div', { class: 'sf-progres' }, [el('span', { style: 'width:' + Math.round(faites / etapes.length * 100) + '%' })]),
            el('div', { class: 'sf-etapes-d' }, etapes.map(function (e) {
                return el('button', { type: 'button', class: 'sf-etape' + (e[1] ? ' sf-etape--ok' : ''), onclick: e[1] ? null : e[2], disabled: e[1] ? true : null }, [el('span', { class: 'sf-etape__rond' }, [icone('check')]), el('span', { text: e[0] })])
            }))
        ])
    }

    // blocObjectifs() : objectifs avec liste de statut (enregistrée dès qu’on la change), bouton
    // Retirer et champ d’ajout. Désactivés ou masqués sans droit de modifier (le serveur revérifie).
    function blocObjectifs() {
        var F = S.fiche, peut = F.droits.modifier
        var s = section('Objectifs', 'objectifs')
        if (!F.objectifs.length) { s.appendChild(vide('Aucun objectif pour l’instant.', peut ? 'Ex. « Retrouver un sommeil régulier », « Prendre rendez-vous chez un dentiste ».' : null)) }
        F.objectifs.forEach(function (o) {
            var statut = el('select', { class: 'sf-statut sf-statut--' + o.statut, 'aria-label': 'Statut de l’objectif « ' + o.titre + ' »', disabled: peut ? null : true }, STATUTS_OBJECTIF.map(function (st) { return el('option', { value: st[0], text: st[1] }) }))
            statut.value = o.statut
            // Changement de statut : PUT immédiat, puis rechargement de la fiche.
            statut.addEventListener('change', function () {
                api('PUT', '/objectifs/' + o.id, { statut: statut.value }).then(function () { toast('Objectif mis à jour'); return rechargerFiche() }).catch(erreur)
            })
            s.appendChild(el('div', { class: 'sf-objectif sf-objectif--' + o.statut }, [statut, el('span', { class: 'sf-objectif__t', text: o.titre }),
                peut ? boutonConfirme('sf-lien sf-lien--danger', 'Retirer', 'Confirmer ?', function () {
                    api('DELETE', '/objectifs/' + o.id).then(function () { toast('Objectif retiré'); return rechargerFiche() }).catch(erreur)
                }) : null]))
        })
        // Ajout : bouton « Ajouter » ou touche Entrée (keydown). trim() retire les espaces ; vide = rien.
        if (peut) {
            var input = el('input', { id: 'sf-nouvel-objectif', class: 'sf-input', placeholder: 'Nouvel objectif…', 'aria-label': 'Nouvel objectif', maxlength: '255' })
            var ajouter = function () {
                var t = input.value.trim()
                if (!t) { input.focus(); return }
                api('POST', '/familles/' + F.famille.id + '/objectifs', { titre: t }).then(function () { toast('Objectif ajouté'); return rechargerFiche() }).catch(erreur)
            }
            input.addEventListener('keydown', function (e) { if (e.key === 'Enter') { ajouter() } })
            s.appendChild(el('div', { class: 'sf-ajout-ligne' }, [input, bouton('sf-btn2', 'Ajouter', ajouter, { ico: 'plus' })]))
        }
        return s
    }

    // blocProchaines() : les 3 prochaines actions (slice(0, 3)) et « Tout voir » s’il y en a plus.
    // Le bouton d’ajout ouvre l’onglet « À faire » avec le formulaire déjà ouvert (allerEditer).
    function blocProchaines() {
        var F = S.fiche, id = F.famille.id
        var ouvertes = F.actions.filter(function (a) { return !a.fait })
        var s = section('Prochaines étapes', 'actions')
        if (!ouvertes.length) { s.appendChild(vide('Rien de prévu.')) }
        ouvertes.slice(0, 3).forEach(function (a) { s.appendChild(ligneAction(a, { responsable: true, apres: rechargerFiche })) })
        s.appendChild(el('div', { class: 'sf-sec__pied' }, [
            bouton('sf-btn2 sf-btn2--petit', 'Ajouter une action ou un rendez-vous', function () { allerEditer('famille/' + id + '/actions', { type: 'action', id: null }) }, { ico: 'plus' }),
            ouvertes.length > 3 ? el('a', { class: 'sf-lien-a', href: '#famille/' + id + '/actions', text: 'Tout voir (' + ouvertes.length + ')' }) : null
        ]))
        return s
    }

    // zoneSensible(f) : suppression définitive (direction ; le serveur vérifie). Bouton en deux
    // temps (boutonConfirme). Après succès : on oublie la fiche et on revient à la liste.
    function zoneSensible(f) {
        return section('Supprimer définitivement le dossier', 'suppression', [
            el('p', { class: 'sf-indic', text: 'Efface la fiche, ses membres, interventions, actions et objectifs. Impossible à annuler.' }),
            el('div', { class: 'sf-actions sf-actions--gauche' }, [boutonConfirme('sf-btn2 sf-btn2--danger', 'Supprimer « ' + f.nom + ' »', 'Cliquer encore pour supprimer définitivement', function () {
                api('DELETE', '/familles/' + f.id).then(function () { toast('Dossier supprimé'); S.fiche = null; aller('familles') }).catch(erreur)
            }, { ico: 'trash' })])
        ], 'sf-sec--danger')
    }

    // ----- Membres -----
    // groupesMembre() : champs d’un membre, même principe que groupesFamille().
    function groupesMembre() {
        return [
            ['Identité', [
                { cle: 'prenom', label: 'Prénom', obligatoire: true },
                { cle: 'nom', label: 'Nom', indication: 'Si différent du nom de la famille' },
                { cle: 'lien', label: 'Lien', liste: 'sf-liens', placeholder: 'Mère, fils…' },
                { cle: 'dateNaissance', label: 'Date de naissance', type: 'date' }
            ]],
            ['Contact', [{ cle: 'telephone', label: 'Téléphone', type: 'tel' }, { cle: 'email', label: 'E-mail', type: 'email' }]],
            ['Santé et suivis', [
                { cle: 'medecinTraitant', label: 'Médecin traitant', aide: 'medecin', placeholder: 'Ex. Dr Lambert, Challans' },
                { cle: 'psychologue', label: 'Psychologue' },
                { cle: 'autresSuivis', label: 'Autres suivis', type: 'textarea', large: true, placeholder: 'Orthophoniste, kiné, PMI, assistante sociale…' }
            ]],
            ['Vie quotidienne et points d’attention', [
                { cle: 'etablissement', label: 'École, travail ou structure', large: true, placeholder: 'Ex. Collège Pierre Garcie, 5e' },
                { cle: 'vigilance', label: 'Points de vigilance', type: 'textarea', large: true, aide: 'vigilance', placeholder: 'Allergie, traitement, handicap, situation à risque…' },
                { cle: 'remarques', label: 'Remarques', type: 'textarea', large: true }
            ]]
        ]
    }
    // formMembre(m, enregistrer) : m = {} pour un nouveau membre, sinon le membre à modifier.
    // Le datalist sf-liens propose les liens de parenté (Mère, Fils…).
    function formMembre(m, enregistrer) {
        var bloc = el('div', { class: 'sf-form sf-form--encart' }, [el('h2', { class: 'sf-h2', text: m.id ? 'Modifier ' + m.prenom : 'Nouveau membre' }), datalist('sf-liens', S.reglages.liens)].concat(fieldsets(groupesMembre(), m)))
        bloc.appendChild(actionsForm(function () { S.edition = null; render() }, 'Enregistrer', function () { return enregistrer(lire(bloc)) }))
        return bloc
    }
    // ongletMembres() : les cartes des membres. S.edition = { type: 'membre', id } dit quel
    // formulaire est ouvert : id null = nouveau membre, sinon la carte de ce membre est
    // remplacée par son formulaire. peut = droit de modifier, calculé par le serveur.
    function ongletMembres() {
        var F = S.fiche, peut = F.droits.modifier, ed = S.edition, w = el('div')
        var enEdition = ed && ed.type === 'membre'
        w.appendChild(el('div', { class: 'sf-barre' }, [
            el('p', { class: 'sf-indic' }, ['Les personnes de la famille et les professionnels qui les suivent.', aide('membres')]),
            peut && !enEdition ? bouton('sf-btn', 'Ajouter un membre', function () { S.edition = { type: 'membre', id: null }; render() }, { ico: 'plus' }) : null
        ]))
        // Formulaire « nouveau membre » en haut.
        if (enEdition && ed.id === null) {
            w.appendChild(formMembre({}, function (v) { return api('POST', '/familles/' + F.famille.id + '/membres', v).then(function () { toast('Membre ajouté'); return rechargerFiche() }) }))
        }
        if (!F.membres.length && !enEdition) { w.appendChild(el('div', { class: 'sf-sec' }, [vide('Aucun membre renseigné.', peut ? 'Commencez par la ou les personnes que vous rencontrez.' : null)])) }
        var grille = el('div', { class: 'sf-membres' })
        // Une carte par membre. En modification, le formulaire prend sa place ; return sort du forEach
        // pour CE membre seulement (on passe au suivant).
        F.membres.forEach(function (m) {
            if (enEdition && ed.id === m.id) {
                w.appendChild(formMembre(m, function (v) { return api('PUT', '/membres/' + m.id, v).then(function () { toast('Membre enregistré'); return rechargerFiche() }) }))
                return
            }
            // sous : « lien · âge (date) ». lignes : seulement les informations remplies.
            var sous = [m.lien, m.dateNaissance ? age(m.dateNaissance) + ' (' + dateFr(m.dateNaissance) + ')' : ''].filter(Boolean).join(' · ')
            var lignes = [['Médecin traitant', m.medecinTraitant], ['Psychologue', m.psychologue], ['Autres suivis', m.autresSuivis], ['École, travail', m.etablissement], ['Téléphone', m.telephone, 'tel'], ['E-mail', m.email, 'mail'], ['Remarques', m.remarques]].filter(function (c) { return c[1] })
            grille.appendChild(el('article', { class: 'sf-membre' }, [
                el('div', { class: 'sf-membre__tete' }, [
                    el('span', { class: 'sf-avatar sf-avatar--m', text: (m.prenom.charAt(0) || '?').toUpperCase() }),
                    el('div', { class: 'sf-membre__id' }, [el('div', { class: 'sf-membre__nom', text: m.prenom + (m.nom ? ' ' + m.nom : '') }), sous ? el('div', { class: 'sf-membre__sous', text: sous }) : null]),
                    peut ? el('div', { class: 'sf-membre__act' }, [
                        bouton('sf-icone-btn', '', function () { S.edition = { type: 'membre', id: m.id }; render() }, { ico: 'edit', title: 'Modifier', 'aria-label': 'Modifier ' + m.prenom }),
                        boutonConfirme('sf-lien sf-lien--danger', 'Retirer', 'Confirmer ?', function () { api('DELETE', '/membres/' + m.id).then(function () { toast('Membre retiré'); return rechargerFiche() }).catch(erreur) })
                    ]) : null
                ]),
                m.vigilance ? el('div', { class: 'sf-vigilance' }, [icone('warning'), el('div', {}, [el('strong', { text: 'À savoir' }), el('div', { class: 'sf-texte', text: m.vigilance })])]) : null,
                lignes.length ? infos(lignes) : el('p', { class: 'sf-dl__vide', text: 'Pas d’autre information.' })
            ]))
        })
        // La grille n’est ajoutée que si elle contient au moins une carte.
        if (grille.children.length) { w.appendChild(grille) }
        return w
    }

    // ----- Suivi (interventions) -----
    // formSuivi(s, enregistrer, annuler) : formulaire d’une intervention ; s = {} pour une nouvelle
    // (date du jour par défaut). « Concerne » vide = toute la famille. La case « Note privée »
    // porte data-cle='prive' : lire() renvoie true ou false.
    function formSuivi(s, enregistrer, annuler) {
        var F = S.fiche, R = S.reglages
        var bloc = el('div', { class: 'sf-form sf-form--encart' }, [
            el('h2', { class: 'sf-h2', text: s.id ? 'Modifier l’intervention' : 'Noter une intervention' }),
            el('div', { class: 'sf-grille sf-grille--4' }, [
                champ({ cle: 'dateSuivi', label: 'Date', type: 'date' }, s.dateSuivi || aujourdhui()),
                champ({ cle: 'type', label: 'Type', type: 'select', options: R.types, aide: 'type', vide: 'Choisir…' }, s.type || ''),
                champ({ cle: 'duree', label: 'Durée', type: 'select', options: DUREES.map(function (m) { return [String(m), duree(m)] }), aide: 'duree', format: function (v) { return duree(parseInt(v, 10)) } }, s.duree ? String(s.duree) : ''),
                champ({ cle: 'membreId', label: 'Concerne', type: 'select', options: F.membres.map(function (m) { return [String(m.id), m.prenom + (m.nom ? ' ' + m.nom : '')] }), vide: 'Toute la famille' }, s.membreId ? String(s.membreId) : '')
            ]),
            champ({ cle: 'texte', label: 'Ce qui s’est passé', type: 'textarea', lignes: 5, large: true, obligatoire: true, placeholder: 'Faits, observations, ce qui a été décidé…' }, s.texte),
            el('div', { class: 'sf-coche-l' }, [el('label', { class: 'sf-coche' }, [el('input', { type: 'checkbox', 'data-cle': 'prive', checked: s.prive ? true : null }), el('span', { text: 'Note privée : visible seulement par moi et la direction' })]), aide('prive')])
        ])
        bloc.appendChild(actionsForm(annuler, 'Enregistrer', function () { return enregistrer(lire(bloc)) }))
        return bloc
    }
    // ongletSuivi() : l’historique des interventions, regroupé par mois, avec filtres (FS = S.filtreSuivi).
    // Les notes privées des autres ne sont jamais envoyées par le serveur (FamillesService.php) :
    // ce fichier affiche simplement ce qu’il reçoit.
    function ongletSuivi() {
        var F = S.fiche, ed = S.edition, w = el('div'), FS = S.filtreSuivi
        // nouveau : le formulaire « nouvelle intervention » est-il ouvert ?
        var nouveau = ed && ed.type === 'suivi' && ed.id === null
        w.appendChild(el('div', { class: 'sf-barre' }, [
            el('p', { class: 'sf-indic' }, ['Une ligne par intervention auprès de la famille.', aide('suivi')]),
            nouveau ? null : bouton('sf-btn', 'Noter une intervention', function () { S.edition = { type: 'suivi', id: null }; render() }, { ico: 'plus' })
        ]))
        if (nouveau) {
            w.appendChild(formSuivi({}, function (v) { return api('POST', '/familles/' + F.famille.id + '/suivis', v).then(function () { toast('Intervention notée'); return rechargerFiche() }) }, function () { S.edition = null; render() }))
        }
        // Aucune intervention : message (sauf si le formulaire est ouvert), et on s’arrête.
        if (!F.suivis.length) { if (!nouveau) { w.appendChild(el('div', { class: 'sf-sec' }, [vide('Aucune intervention notée pour l’instant.', 'Après chaque rencontre, un appel ou une démarche : « Noter une intervention ».')])) } return w }

        // membres : dictionnaire id → prénom, pour afficher « Concerne Léa ».
        var membres = {}
        F.membres.forEach(function (m) { membres[m.id] = m.prenom })
        // Filtres seulement au-delà de 3 interventions. types : les types réellement utilisés
        // (clés d’un objet = sans doublon). sel() fabrique une liste déroulante liée à FS[cle].
        if (F.suivis.length > 3) {
            var types = {}
            F.suivis.forEach(function (s) { if (s.type) { types[s.type] = true } })
            var sel = function (cle, vide, options) {
                var x = el('select', { class: 'sf-input sf-input--filtre' + (FS[cle] ? ' sf-input--actif' : ''), 'aria-label': vide }, [el('option', { value: '', text: vide })].concat(options.map(function (o) { return el('option', { value: o[0], text: o[1] }) })))
                x.value = FS[cle]
                x.addEventListener('change', function () { FS[cle] = x.value; render() })
                return x
            }
            w.appendChild(el('div', { class: 'sf-filtres' }, [
                sel('type', 'Tous les types', Object.keys(types).map(function (t) { return [t, t] })),
                sel('membre', 'Toute la famille', F.membres.map(function (m) { return [String(m.id), m.prenom] }))
            ]))
        }
        // On garde les interventions qui passent les filtres (un filtre vide laisse tout passer).
        var visibles = F.suivis.filter(function (s) { return (!FS.type || s.type === FS.type) && (!FS.membre || String(s.membreId) === FS.membre) })
        if (!visibles.length) { w.appendChild(vide('Aucune intervention ne correspond.')) }
        // Les interventions arrivent de la plus récente à la plus ancienne. À chaque changement de mois
        // (slice(0, 7) = 'AAAA-MM'), on insère un intertitre « Novembre 2026 ».
        var moisCourant = ''
        visibles.forEach(function (s) {
            var mois = s.dateSuivi.slice(0, 7)
            if (mois !== moisCourant) { moisCourant = mois; w.appendChild(el('div', { class: 'sf-mois', text: capitale(moisFr(mois)) })) }
            // Intervention en cours de modification : le formulaire prend sa place.
            if (ed && ed.type === 'suivi' && ed.id === s.id) {
                w.appendChild(formSuivi(s, function (v) { return api('PUT', '/suivis/' + s.id, v).then(function () { toast('Intervention modifiée'); return rechargerFiche() }) }, function () { S.edition = null; render() }))
                return
            }
            // La note : en-tête (date, type, durée, membre, « Privée »), texte, auteur et boutons.
            // s.peutModifier vient du serveur, qui le revérifie lors du PUT / DELETE.
            w.appendChild(el('article', { class: 'sf-note' + (s.prive ? ' sf-note--privee' : '') }, [
                el('div', { class: 'sf-note__tete' }, [
                    el('span', { class: 'sf-note__date', text: dateFr(s.dateSuivi) }),
                    s.type ? pastille(s.type, 'acc') : null,
                    s.duree ? el('span', { class: 'sf-meta', text: duree(s.duree) }) : null,
                    s.membreId && membres[s.membreId] ? el('span', { class: 'sf-meta', text: 'Concerne ' + membres[s.membreId] }) : null,
                    s.prive ? pastille('Privée', 'violet', 'lock') : null
                ]),
                el('p', { class: 'sf-texte', text: s.texte }),
                el('div', { class: 'sf-note__pied' }, [
                    el('span', { text: 'Par ' + (s.auteur === moi ? 'vous' : s.auteurNom) }),
                    s.peutModifier ? el('span', { class: 'sf-note__act' }, [
                        bouton('sf-lien', 'Modifier', function () { S.edition = { type: 'suivi', id: s.id }; render() }),
                        boutonConfirme('sf-lien sf-lien--danger', 'Supprimer', 'Confirmer la suppression ?', function () { api('DELETE', '/suivis/' + s.id).then(function () { toast('Intervention supprimée'); return rechargerFiche() }).catch(erreur) })
                    ]) : null
                ])
            ]))
        })
        return w
    }

    // ----- À faire (actions et rendez-vous) -----
    // formAction(a, enregistrer, annuler) : formulaire d’une action ou d’un rendez-vous.
    // « Qui s’en charge ? » propose F.personnes (l’équipe, fournie par le serveur).
    // Pour une nouvelle action, on se propose soi-même par défaut (moi).
    function formAction(a, enregistrer, annuler) {
        var F = S.fiche
        var bloc = el('div', { class: 'sf-form sf-form--encart' }, [
            el('h2', { class: 'sf-h2', text: a.id ? 'Modifier' : 'Ajouter une action ou un rendez-vous' }),
            el('div', { class: 'sf-grille sf-grille--4' }, [
                champ({ cle: 'genre', label: 'Genre', type: 'select', options: [['action', 'Action à faire'], ['rdv', 'Rendez-vous']], vide: 'Choisir…' }, a.genre || 'action'),
                champ({ cle: 'echeance', label: 'Date', type: 'date', indication: 'Obligatoire pour un rendez-vous' }, a.echeance || ''),
                champ({ cle: 'heure', label: 'Heure', type: 'time' }, a.heure || ''),
                champ({ cle: 'responsable', label: 'Qui s’en charge ?', type: 'select', options: F.personnes.map(function (p) { return [p.id, p.id === moi ? p.nom + ' (vous)' : p.nom] }), vide: 'Personne en particulier' }, a.id ? (a.responsable || '') : moi)
            ]),
            champ({ cle: 'titre', label: 'Quoi ?', large: true, obligatoire: true, placeholder: 'Ex. Appeler l’école pour le bilan de Hugo' }, a.titre || '')
        ])
        bloc.appendChild(actionsForm(annuler, 'Enregistrer', function () { return enregistrer(lire(bloc)) }))
        return bloc
    }
    // ongletActions() : l’onglet « À faire ». Les actions ouvertes, puis, sur demande
    // (S.voirFaites), celles déjà faites.
    function ongletActions() {
        var F = S.fiche, ed = S.edition, w = el('div')
        var nouveau = ed && ed.type === 'action' && ed.id === null
        w.appendChild(el('div', { class: 'sf-barre' }, [
            el('p', { class: 'sf-indic' }, ['Les prochaines étapes de l’accompagnement, pour toute l’équipe.', aide('actions')]),
            nouveau ? null : bouton('sf-btn', 'Ajouter', function () { S.edition = { type: 'action', id: null }; render() }, { ico: 'plus' })
        ]))
        // Création : si la personne choisie n’est pas soi, le serveur la prévient ; le message le confirme.
        if (nouveau) {
            w.appendChild(formAction({}, function (v) { return api('POST', '/familles/' + F.famille.id + '/actions', v).then(function (r) { toast(r.responsable && r.responsable !== moi ? 'Ajouté : la personne est prévenue' : 'Ajouté'); return rechargerFiche() }) }, function () { S.edition = null; render() }))
        }
        // Deux tableaux grâce à filter : ouvertes (!a.fait) et faites.
        var ouvertes = F.actions.filter(function (a) { return !a.fait }), faites = F.actions.filter(function (a) { return a.fait })
        // ligne(a) : formulaire si l’action est en modification, sinon ligneAction avec ses boutons.
        // Modifier : si pas faite. Supprimer : selon a.peutSupprimer, calculé et revérifié par le serveur.
        var ligne = function (a) {
            if (ed && ed.type === 'action' && ed.id === a.id) {
                return formAction(a, function (v) { return api('PUT', '/actions/' + a.id, v).then(function () { toast('Enregistré'); return rechargerFiche() }) }, function () { S.edition = null; render() })
            }
            return ligneAction(a, {
                responsable: true, apres: rechargerFiche, outils: [
                    a.fait ? null : bouton('sf-lien', 'Modifier', function () { S.edition = { type: 'action', id: a.id }; render() }),
                    a.peutSupprimer ? boutonConfirme('sf-lien sf-lien--danger', 'Supprimer', 'Confirmer ?', function () { api('DELETE', '/actions/' + a.id).then(function () { toast('Supprimé'); return rechargerFiche() }).catch(erreur) }) : null
                ]
            })
        }
        // Les actions ouvertes (map transforme chaque action en ligne), ou un message si aucune.
        var s = el('div', { class: 'sf-sec' }, ouvertes.length ? ouvertes.map(ligne) : [vide('Rien à faire pour l’instant.', 'Ajoutez la prochaine étape : un appel, une démarche, un rendez-vous.')])
        w.appendChild(s)
        // Bouton déplier / replier « ce qui est fait » ; aria-expanded indique l’état.
        if (faites.length) {
            w.appendChild(bouton('sf-lien sf-lien--depli', (S.voirFaites ? 'Masquer' : 'Afficher') + ' ce qui est fait (' + faites.length + ')', function () { S.voirFaites = !S.voirFaites; render() }, { 'aria-expanded': S.voirFaites ? 'true' : 'false' }))
            if (S.voirFaites) { w.appendChild(el('div', { class: 'sf-sec sf-sec--discret' }, faites.map(ligne))) }
        }
        return w
    }

    // ----- Équipe (qui a accès à cette famille) -----
    // reglesAcces() : encadré repliable <details>/<summary> (natif en HTML, sans JavaScript)
    // qui rappelle les règles d’accès et les précautions RGPD.
    function reglesAcces() {
        return el('details', { class: 'sf-details' }, [
            el('summary', { text: 'Qui voit quoi ? Règles d’accès et données sensibles' }),
            el('ul', { class: 'sf-liste' }, [
                el('li', { text: 'La direction voit toutes les familles, toutes les notes et le journal.' }),
                el('li', { text: 'Chaque personne ci-dessous voit cette famille, et uniquement les familles qui lui sont confiées. Les autres comptes ne savent même pas qu’elle existe.' }),
                el('li', { text: '« Peut modifier » : peut aussi changer les coordonnées, les membres et les objectifs. Sinon : lecture, interventions et actions.' }),
                el('li', { text: 'Une note privée n’est visible que par son auteur et la direction.' }),
                el('li', { text: 'Chaque consultation, impression et modification est inscrite au journal.' })
            ]),
            el('h3', { class: 'sf-h3', text: 'Données sensibles (RGPD)' }),
            el('ul', { class: 'sf-liste' }, [
                el('li', { text: 'Ces fiches contiennent des données de santé : n’ouvrez l’accès qu’aux personnes qui suivent réellement la famille, et retirez-le quand leur intervention s’arrête.' }),
                el('li', { text: 'Point en cours de vérification : obligations RGPD de l’association et besoin éventuel d’un hébergeur certifié HDS (hébergeur de données de santé).' })
            ])
        ])
    }
    // ongletEquipe() : les personnes qui ont accès à la famille. Direction : formulaire d’ajout et,
    // par personne, fonction, « peut modifier », référent·e, retrait. Autres : liste en lecture.
    // Masquer ces boutons aux autres ne protège rien : c’est le serveur qui refuse ces actions.
    function ongletEquipe() {
        var F = S.fiche, f = F.famille, dir = F.droits.direction, w = el('div')
        w.appendChild(el('div', { class: 'sf-barre' }, [el('p', { class: 'sf-indic' }, [dir ? 'Les personnes qui ont accès à cette famille.' : 'Les personnes qui suivent cette famille avec vous.', aide('equipe')])]))
        w.appendChild(reglesAcces())
        if (dir) { w.appendChild(formAjoutAcces()) }
        var s = el('div', { class: 'sf-sec' })
        if (!F.equipe.length) { s.appendChild(vide('Personne d’autre que la direction n’a accès à cette famille.', dir ? 'Ouvrez l’accès aux intervenants qui la suivent avec le formulaire ci-dessus.' : null)) }
        // Suggestions (Psychologue…) pour les champs « Fonction ».
        var fonctions = datalist('sf-fonctions', S.reglages.fonctions)
        s.appendChild(fonctions)
        // Une ligne par personne. estRef : est-elle référent·e de la famille ?
        F.equipe.forEach(function (a) {
            var estRef = f.referent === a.userId
            var nom = el('div', { class: 'sf-ligne__t' }, [a.nom + (a.userId === moi ? ' (vous)' : ''), estRef ? pastille('Référent·e', 'acc', 'star') : null])
            // Hors direction : ligne en lecture seule ; return passe à la personne suivante.
            if (!dir) {
                s.appendChild(el('div', { class: 'sf-ligne' }, [el('span', { class: 'sf-avatar', text: initiale(a.nom) }), el('div', { class: 'sf-ligne__b' }, [nom, el('div', { class: 'sf-ligne__m', text: [a.fonction, a.peutModifier ? 'peut modifier la fiche' : ''].filter(Boolean).join(' · ') })])]))
                return
            }
            // Direction : champs modifiables. Leurs valeurs sont lues au clic sur « Enregistrer »
            // (fonction.value, modif.checked), grâce à la fermeture.
            var fonction = el('input', { class: 'sf-input sf-input--petit', value: a.fonction || '', list: 'sf-fonctions', 'aria-label': 'Fonction de ' + a.nom, placeholder: 'Fonction' })
            var modif = el('input', { type: 'checkbox', checked: a.peutModifier ? true : null })
            s.appendChild(el('div', { class: 'sf-ligne' }, [
                el('span', { class: 'sf-avatar', text: initiale(a.nom) }),
                el('div', { class: 'sf-ligne__b' }, [nom, el('div', { class: 'sf-ligne__m', text: 'Accès depuis le ' + dateFr(iso(new Date(a.createdAt * 1000))) })]),
                el('div', { class: 'sf-ligne__r' }, [
                    fonction,
                    el('label', { class: 'sf-coche sf-coche--petit' }, [modif, el('span', { text: 'Peut modifier' })]), aide('peutModifier'),
                    bouton('sf-btn2 sf-btn2--petit', 'Enregistrer', function () {
                        api('PUT', '/acces/' + a.id, { fonction: fonction.value, peutModifier: modif.checked }).then(function () { toast('Accès mis à jour'); return rechargerFiche() }).catch(erreur)
                    }),
                    // Selon le cas : retirer le rôle de référent·e, ou le confier à cette personne.
                    estRef
                        ? bouton('sf-lien', 'Retirer le rôle de référent·e', function () { definirReferent('') })
                        : bouton('sf-lien', 'Nommer référent·e', function () { definirReferent(a.userId) }, { ico: 'star' }),
                    boutonConfirme('sf-lien sf-lien--danger', 'Retirer l’accès', 'Confirmer ?', function () { api('DELETE', '/acces/' + a.id).then(function () { toast('Accès retiré'); return rechargerFiche() }).catch(erreur) })
                ])
            ]))
        })
        w.appendChild(s)
        return w
    }
    // definirReferent(uid) : nomme la personne référent·e (uid vide = plus personne), puis recharge.
    function definirReferent(uid) {
        api('PUT', '/familles/' + S.fiche.famille.id + '/referent', { userId: uid }).then(function () { toast(uid ? 'Référent·e désigné·e' : 'Plus de référent·e'); return rechargerFiche() }).catch(erreur)
    }
    // Ajout d’une personne : recherche dans les comptes Nextcloud (au fil de la frappe), fonction, droit de modifier.
    function formAjoutAcces() {
        // choisi : identifiant du compte cliqué dans les propositions (null tant qu’aucun choix).
        // minuterie : pour attendre la fin de la frappe.
        var choisi = null, minuterie = null
        var recherche = el('input', { id: 'sf-ajout-qui', class: 'sf-input', type: 'search', placeholder: 'Nom ou identifiant du compte', autocomplete: 'off' })
        var propositions = el('div', { class: 'sf-propositions' })
        var fonction = el('input', { id: 'sf-ajout-fonction', class: 'sf-input', list: 'sf-fonctions-ajout', placeholder: 'Ex. Psychologue' })
        var modif = el('input', { type: 'checkbox' })
        // Recherche au fil de la frappe avec « anti-rebond » (debounce) : à chaque touche on annule
        // la minuterie précédente (clearTimeout) et on en relance une de 250 ms. La requête ne part
        // que quand on cesse de taper : pas d’appel serveur à chaque lettre.
        recherche.addEventListener('input', function () {
            choisi = null
            clearTimeout(minuterie)
            var q = recherche.value.trim()
            // Moins de 2 caractères : on vide les propositions.
            if (q.length < 2) { propositions.innerHTML = ''; return }
            minuterie = setTimeout(function () {
                // encodeURIComponent protège le texte placé dans l’URL (espaces, &, accents…).
                api('GET', '/utilisateurs?q=' + encodeURIComponent(q)).then(function (r) {
                    propositions.innerHTML = ''
                    // deja : personnes déjà dans l’équipe, retirées des propositions. Un clic sur une proposition
                    // mémorise le compte (choisi) et affiche son nom dans le champ.
                    var deja = {}
                    S.fiche.equipe.forEach(function (a) { deja[a.userId] = true })
                    r.filter(function (u) { return !deja[u.id] }).forEach(function (u) {
                        propositions.appendChild(bouton('sf-proposition', u.nom + ' (' + u.id + ')', function () { choisi = u.id; recherche.value = u.nom; propositions.innerHTML = '' }))
                    })
                    if (!propositions.children.length) { propositions.appendChild(el('div', { class: 'sf-indic', text: 'Aucun compte trouvé (ou déjà dans l’équipe).' })) }
                }).catch(erreur)
            }, 250)
        })
        // Le formulaire. « Donner l’accès » exige un compte choisi dans la liste (pas juste du texte tapé).
        // Le serveur vérifie que c’est la direction, puis prévient la personne par notification.
        return el('div', { class: 'sf-form sf-form--encart' }, [
            el('h2', { class: 'sf-h2', text: 'Ouvrir l’accès à une personne' }),
            el('div', { class: 'sf-grille' }, [
                el('div', { class: 'sf-champ sf-champ--rel' }, [el('div', { class: 'sf-lbl-l' }, [el('label', { class: 'sf-lbl', for: 'sf-ajout-qui', text: 'Compte Nextcloud' })]), recherche, propositions]),
                el('div', { class: 'sf-champ' }, [el('div', { class: 'sf-lbl-l' }, [el('label', { class: 'sf-lbl', for: 'sf-ajout-fonction', text: 'Fonction' })]), fonction, datalist('sf-fonctions-ajout', S.reglages.fonctions)])
            ]),
            el('div', { class: 'sf-coche-l' }, [el('label', { class: 'sf-coche' }, [modif, el('span', { text: 'Peut modifier la fiche (coordonnées, membres, objectifs)' })]), aide('peutModifier')]),
            el('div', { class: 'sf-actions' }, [bouton('sf-btn', 'Donner l’accès', function () {
                if (!choisi) { toast('Choisissez un compte dans la liste proposée', true); recherche.focus(); return }
                api('POST', '/familles/' + S.fiche.famille.id + '/acces', { userId: choisi, fonction: fonction.value, peutModifier: modif.checked })
                    .then(function (a) { toast('Accès ouvert à ' + a.nom + ' (prévenu par notification)'); return rechargerFiche() }).catch(erreur)
            })])
        ])
    }

    // ----- Journal -----
    // tableJournal(lignes, avecFamille) : lignes du journal (date, personne, action, détail).
    // ACTIONS[j.action] traduit le code en libellé (à défaut, le code brut). avecFamille : lien vers
    // la famille (journal général), ou « famille supprimée » si elle n’existe plus.
    function tableJournal(lignes, avecFamille) {
        if (!lignes.length) { return vide('Rien dans le journal.') }
        return el('div', { class: 'sf-journal' }, lignes.map(function (j) {
            return el('div', { class: 'sf-journal__l' }, [
                el('span', { class: 'sf-journal__q', text: quand(j.createdAt) }),
                el('span', { class: 'sf-journal__p', text: j.nom }),
                el('span', { class: 'sf-journal__a' + (j.action === 'consultation' ? ' sf-journal__a--discret' : '') }, [el('strong', { text: ACTIONS[j.action] || j.action }), j.detail ? ' — ' + j.detail : '']),
                avecFamille ? (j.famille ? el('a', { class: 'sf-journal__f', href: '#famille/' + j.familleId, text: j.famille }) : el('span', { class: 'sf-journal__f sf-dl__vide', text: j.familleId ? 'famille supprimée' : '' })) : null
            ])
        }))
    }
    // ongletJournal() : journal d’une famille (direction). S.journal a été chargé par route().
    function ongletJournal() {
        return el('div', {}, [
            el('div', { class: 'sf-barre' }, [el('p', { class: 'sf-indic' }, ['Qui a consulté, imprimé ou modifié cette fiche (200 dernières lignes).', aide('journal')])]),
            el('div', { class: 'sf-sec' }, [tableJournal(S.journal || [], false)])
        ])
    }
    // vueJournal() : journal de toutes les familles (direction), filtré dans le navigateur
    // (même principe que la recherche des familles).
    function vueJournal() {
        var q = norm(S.filtreJournal)
        var lignes = (S.journal || []).filter(function (j) { return !q || norm([j.nom, j.famille, ACTIONS[j.action], j.detail].join(' ')).indexOf(q) !== -1 })
        var recherche = el('input', { id: 'sf-recherche-journal', class: 'sf-input sf-input--recherche', type: 'search', value: S.filtreJournal, placeholder: 'Filtrer par personne, famille ou action…', 'aria-label': 'Filtrer le journal' })
        recherche.addEventListener('input', function () { S.filtreJournal = recherche.value; render() })
        return el('div', {}, [
            entete('Journal des accès', 'Consultations, impressions et modifications, toutes familles (200 dernières lignes).', [], 'journal'),
            el('div', { class: 'sf-outils' }, [recherche]),
            el('div', { class: 'sf-sec' }, [tableJournal(lignes, true)])
        ])
    }

    // =====================================================================
    // IMPRESSION de la fiche : un bloc dédié, hors de l’appli, seul visible à l’impression
    // =====================================================================
    // imprimer(contenu) : place le contenu dans la zone #sf-print (créée au 1er appel, dans <body>),
    // puis ouvre la fenêtre d’impression. Le CSS @media print ne montre que cette zone.
    // Le délai de 60 ms laisse le navigateur dessiner la zone avant d’imprimer.
    function imprimer(contenu) {
        var z = document.getElementById('sf-print')
        if (!z) { z = el('div', { id: 'sf-print' }); document.body.appendChild(z) }
        z.innerHTML = ''
        z.appendChild(contenu)
        setTimeout(function () { window.print() }, 60)
    }
    function imprimerFiche() {
        // On trace d’abord l’impression au journal ; si ça échoue, on n’imprime pas.
        api('POST', '/familles/' + S.fiche.famille.id + '/impression').then(function () { imprimer(dossierImprimable(S.fiche)) }).catch(erreur)
    }
    // dlImp(lignes) : tableau « libellé | valeur » pour le papier ; lignes vides retirées.
    function dlImp(lignes) {
        return el('table', { class: 'sf-imp-t' }, lignes.filter(function (l) { return l[1] }).map(function (l) { return el('tr', {}, [el('th', { text: l[0] }), el('td', { text: String(l[1]) })]) }))
    }
    // dossierImprimable(F) : toute la fiche en version papier : mention CONFIDENTIEL, coordonnées,
    // situation, objectifs, membres, équipe, actions à faire, interventions (notes privées signalées).
    // Les blocs vides valent null et sont ignorés par el().
    function dossierImprimable(F) {
        var f = F.famille, membres = {}
        F.membres.forEach(function (m) { membres[m.id] = m.prenom })
        return el('div', { class: 'sf-imp' }, [
            el('p', { class: 'sf-imp__conf', text: 'CONFIDENTIEL · données personnelles et de santé. À ranger sous clé, à détruire après usage.' }),
            el('h1', { text: f.nom }),
            el('p', { class: 'sf-imp__meta', text: 'Imprimé le ' + new Date().toLocaleString('fr-FR') + ' par ' + monNom + (f.statut === 'clos' ? ' · accompagnement clos' + (f.dateFin ? ' le ' + dateFr(f.dateFin) : '') : '') }),
            el('h2', { text: 'Coordonnées et accompagnement' }),
            dlImp([['Téléphone', f.telephone], ['E-mail', f.email], ['Adresse', [f.adresse, f.commune].filter(Boolean).join(', ')], ['Territoire', f.territoire], ['Début', dateFr(f.dateEntree)], ['Orientée par', f.orientation], ['Besoins', f.besoins.join(', ')], ['Référent·e', f.referentNom], ['Consentement', f.consentement ? 'recueilli le ' + dateFr(f.consentement) : 'non renseigné'], ['Fin', f.dateFin ? dateFr(f.dateFin) + (f.motifFin ? ' · ' + f.motifFin : '') : '']]),
            f.situation ? [el('h2', { text: 'Situation' }), el('p', { class: 'sf-imp__txt', text: f.situation })] : null,
            F.objectifs.length ? [el('h2', { text: 'Objectifs' }), el('ul', {}, F.objectifs.map(function (o) { return el('li', { text: o.titre + ' · ' + { en_cours: 'en cours', atteint: 'atteint', abandonne: 'abandonné' }[o.statut] }) }))] : null,
            el('h2', { text: 'Membres' }),
            F.membres.length ? F.membres.map(function (m) {
                return [el('h3', { text: m.prenom + (m.nom ? ' ' + m.nom : '') + (m.lien ? ' · ' + m.lien : '') }), dlImp([['Naissance', m.dateNaissance ? dateFr(m.dateNaissance) + ' (' + age(m.dateNaissance) + ')' : ''], ['Téléphone', m.telephone], ['E-mail', m.email], ['École, travail', m.etablissement], ['Médecin traitant', m.medecinTraitant], ['Psychologue', m.psychologue], ['Autres suivis', m.autresSuivis], ['Points de vigilance', m.vigilance], ['Remarques', m.remarques]])]
            }) : el('p', { text: 'Aucun membre renseigné.' }),
            el('h2', { text: 'Équipe' }),
            el('ul', {}, F.equipe.length ? F.equipe.map(function (a) { return el('li', { text: a.nom + (a.fonction ? ' · ' + a.fonction : '') + (f.referent === a.userId ? ' · référent·e' : '') }) }) : [el('li', { text: 'Direction seulement' })]),
            F.actions.some(function (a) { return !a.fait }) ? [el('h2', { text: 'À faire' }), el('ul', {}, F.actions.filter(function (a) { return !a.fait }).map(function (a) { return el('li', { text: (a.echeance ? dateFr(a.echeance) + (a.heure ? ' ' + heureFr(a.heure) : '') + ' · ' : '') + (a.genre === 'rdv' ? 'RDV : ' : '') + a.titre + (a.responsableNom ? ' (' + a.responsableNom + ')' : '') }) }))] : null,
            el('h2', { text: 'Suivi (' + pluriel(F.suivis.length, 'intervention') + ')' }),
            F.suivis.map(function (s) {
                return el('div', { class: 'sf-imp__note' }, [
                    el('p', { class: 'sf-imp__nt', text: [dateFr(s.dateSuivi), s.type, duree(s.duree), s.membreId && membres[s.membreId] ? 'concerne ' + membres[s.membreId] : '', 'par ' + s.auteurNom, s.prive ? 'NOTE PRIVÉE' : ''].filter(Boolean).join(' · ') }),
                    el('p', { class: 'sf-imp__txt', text: s.texte })
                ])
            })
        ])
    }
    // =====================================================================
    // GUIDE D’UTILISATION
    // =====================================================================
    // vueGuide() : le mode d’emploi. Fonctions locales : partie (section + entrée du sommaire),
    // p (paragraphe), etapes (liste numérotée), puces, tableau. partie() remplit « parties »,
    // qui sert ensuite à construire le sommaire.
    function vueGuide() {
        var parties = []
        var partie = function (id, titre, contenu) { parties.push([id, titre]); return el('section', { class: 'sf-sec sf-guide', id: 'sf-g-' + id }, [el('h2', { class: 'sf-h2', text: titre })].concat(contenu)) }
        var p = function (t) { return el('p', { text: t }) }
        var etapes = function (liste) { return el('ol', { class: 'sf-etapes' }, liste.map(function (t) { return el('li', {}, Array.isArray(t) ? [el('strong', { text: t[0] }), ' ' + t[1]] : [t]) })) }
        var puces = function (liste) { return el('ul', { class: 'sf-liste' }, liste.map(function (t) { return el('li', {}, Array.isArray(t) ? [el('strong', { text: t[0] }), ' ' + t[1]] : [t]) })) }
        var tableau = function (tete, lignes) { return el('div', { class: 'sf-table-w' }, [el('table', { class: 'sf-table' }, [el('thead', {}, [el('tr', {}, tete.map(function (t) { return el('th', { text: t }) }))]), el('tbody', {}, lignes.map(function (l) { return el('tr', {}, l.map(function (c, i) { return el(i ? 'td' : 'th', { text: c }) })) }))])]) }

        // Le contenu du guide, section par section.
        var contenu = [
            partie('bref', 'En bref', [
                p('L’appli Familles rassemble le dossier de chaque famille accompagnée : qui sont ses membres, ce qui a été fait, ce qui reste à faire, et qui l’accompagne.'),
                p('Chaque intervenant ne voit que les familles qui lui sont confiées. La direction voit tout.'),
                p('Partout, les petits « ? » expliquent l’élément à côté duquel ils se trouvent : cliquez dessus.')
            ]),
            partie('quotidien', 'Au quotidien', [
                etapes([
                    ['Ouvrez l’Accueil.', 'Vous y trouvez vos actions et rendez-vous (en retard en rouge).'],
                    ['Après chaque rencontre, un appel ou une démarche,', 'ouvrez la fiche › onglet Suivi › « Noter une intervention » : la date, le type, la durée et ce qui s’est passé.'],
                    ['Notez la suite', 'dans l’onglet « À faire » : un rendez-vous, un appel à passer… et confiez-la à la bonne personne, qui sera prévenue.'],
                    ['Cochez le rond', 'quand une action est faite.']
                ])
            ]),
            partie('ouvrir', 'Ouvrir un dossier (direction)', [
                etapes([
                    ['Familles › Nouvelle famille.', 'Le nom suffit ; le reste peut attendre.'],
                    ['Ajoutez les membres', 'dans l’onglet Membres, avec leur médecin traitant et les points de vigilance.'],
                    ['Onglet Équipe :', 'ouvrez l’accès aux intervenants qui suivent la famille, puis nommez un·e référent·e.'],
                    ['Notez la date du consentement', 'dès que la famille a donné son accord (bouton Modifier).'],
                    ['Fixez un ou deux objectifs', 'dans la Synthèse.']
                ]),
                p('Sur une fiche récente, l’encadré « Pour bien démarrer » rappelle ce qui reste à faire.')
            ]),
            partie('onglets', 'Les parties d’une fiche', [tableau(['Onglet', 'Ce qu’on y trouve'], [
                ['Synthèse', 'Situation, objectifs, prochaines étapes, coordonnées, besoins, consentement.'],
                ['Membres', 'Chaque personne de la famille, ses professionnels de santé, ses points de vigilance.'],
                ['Suivi', 'L’historique des interventions, du plus récent au plus ancien.'],
                ['À faire', 'Les actions et rendez-vous à venir, et ce qui a été fait.'],
                ['Équipe', 'Qui a accès à la famille, avec quels droits ; le ou la référent·e.'],
                ['Journal', 'Qui a consulté, imprimé ou modifié la fiche (direction).']
            ])]),
            partie('droits', 'Qui voit quoi ?', [tableau(['', 'Direction', 'Intervenant « peut modifier »', 'Intervenant'], [
                ['Familles visibles', 'toutes', 'les siennes', 'les siennes'],
                ['Lire la fiche, noter une intervention, gérer les actions', 'oui', 'oui', 'oui'],
                ['Modifier coordonnées, membres, objectifs', 'oui', 'oui', 'non'],
                ['Lire les notes privées des autres', 'oui', 'non', 'non'],
                ['Ouvrir ou retirer un accès, nommer le référent, clore, supprimer', 'oui', 'non', 'non'],
                ['Journal des accès', 'oui', 'non', 'non']
            ]), p('Une famille à laquelle vous n’avez pas accès n’apparaît nulle part : même son existence vous est cachée.')]),
            partie('rgpd', 'Données sensibles : les bons réflexes', [
                puces([
                    ['Consentement :', 'la famille doit savoir ce qui est noté et qui y a accès. Notez la date de son accord sur la fiche.'],
                    ['Le nécessaire, rien de plus :', 'n’écrivez que ce qui sert à l’accompagnement, de façon factuelle et respectueuse.'],
                    ['Notes privées :', 'pour ce qui ne doit pas circuler dans l’équipe.'],
                    ['Accès :', 'retirez l’accès d’un intervenant dès que son intervention s’arrête.'],
                    ['Papier :', 'une fiche imprimée se range sous clé et se détruit après usage. Chaque impression est notée au journal.'],
                    ['Poste de travail :', 'verrouillez votre écran quand vous vous absentez.'],
                    ['Droits des familles :', 'une famille peut demander à voir ses données (Imprimer la fiche) ou leur effacement (Supprimer, direction).']
                ]),
                bandeau('alerte', 'En cours de vérification par l’association : obligations RGPD et besoin éventuel d’un hébergeur certifié HDS (données de santé). En attendant, n’utilisez que des données fictives.')
            ]),
            partie('fin', 'Fin d’un accompagnement', [
                etapes([
                    ['Clore', '(direction, en haut de la fiche) : date de fin et motif. La fiche passe dans « Closes » ; elle reste consultable et peut être rouverte.'],
                    ['Retirez les accès', 'des intervenants qui n’en ont plus besoin (onglet Équipe).'],
                    ['Au terme de la durée de conservation', 'décidée par l’association, supprimez le dossier (bas de la Synthèse).']
                ])
            ])
        ]
        // Sommaire : un bouton par partie ; scrollIntoView fait défiler en douceur jusqu’à la section.
        var sommaire = el('nav', { class: 'sf-sommaire', 'aria-label': 'Sommaire du guide' }, parties.map(function (x) {
            return bouton('sf-chip sf-chip--lien', x[1], function () { var c = document.getElementById('sf-g-' + x[0]); if (c) { c.scrollIntoView({ behavior: 'smooth', block: 'start' }) } })
        }))
        return el('div', {}, [entete('Guide d’utilisation', 'Tout ce qu’il faut savoir pour bien utiliser l’appli Familles.', []), sommaire].concat(contenu))
    }

    // ---------- démarrage ----------
    // Point de départ : d’abord les réglages (listes de choix), puis route() affiche l’écran
    // indiqué par l’adresse. .catch(erreur) affiche toute erreur éventuelle.
    chargerReglages().then(route).catch(erreur)
// Fin de l’IIFE : les () appellent tout de suite la fonction ouverte en haut du fichier.
})()
