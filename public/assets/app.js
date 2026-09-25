'use strict';

const CSRF = document.querySelector('meta[name="csrf"]')?.content ?? '';

// Envoie un formulaire POST en arrière-plan et renvoie la réponse JSON
async function envoyer(url, donnees) {
    const corps = new FormData();
    corps.append('csrf', CSRF);
    Object.entries(donnees).forEach(([cle, valeur]) => corps.append(cle, valeur));
    const reponse = await fetch(url, { method: 'POST', body: corps, headers: { 'X-Requested-With': 'fetch' } });
    const json = await reponse.json().catch(() => ({ ok: false, message: 'Réponse inattendue du serveur.' }));
    if (!reponse.ok || !json.ok) {
        throw new Error(json.message || 'Erreur ' + reponse.status);
    }
    return json;
}

/* ---------- Petits comportements de formulaire ---------- */

document.querySelectorAll('.js-autosubmit').forEach((champ) => {
    champ.addEventListener('change', () => champ.form.requestSubmit());
});

document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (evt) => {
        if (!confirm(form.dataset.confirm)) {
            evt.preventDefault();
        }
    });
});

// Empêche le double envoi d'un formulaire classique
document.querySelectorAll('form[method="post"]').forEach((form) => {
    form.addEventListener('submit', () => {
        setTimeout(() => form.querySelectorAll('button').forEach((b) => { b.disabled = true; }), 0);
    });
});

/* ---------- Kanban : glisser-déposer ---------- */

function mettreAJourCompteurs() {
    document.querySelectorAll('.kanban-col').forEach((col) => {
        const nb = col.querySelectorAll('.carte-ticket').length;
        col.querySelector('.compteur').textContent = nb;
        col.querySelector('.kanban-vide').hidden = nb > 0;
    });
}

let carteDeplacee = null;

document.querySelectorAll('.carte-ticket').forEach((carte) => {
    carte.addEventListener('dragstart', (evt) => {
        carteDeplacee = carte;
        carte.classList.add('deplacement');
        evt.dataTransfer.effectAllowed = 'move';
        evt.dataTransfer.setData('text/plain', carte.dataset.id);
    });
    carte.addEventListener('dragend', () => {
        carte.classList.remove('deplacement');
        carteDeplacee = null;
    });
});

document.querySelectorAll('.kanban-col').forEach((col) => {
    col.addEventListener('dragover', (evt) => {
        if (carteDeplacee) {
            evt.preventDefault();
            col.classList.add('survol');
        }
    });
    col.addEventListener('dragleave', (evt) => {
        if (!col.contains(evt.relatedTarget)) {
            col.classList.remove('survol');
        }
    });
    col.addEventListener('drop', async (evt) => {
        evt.preventDefault();
        col.classList.remove('survol');
        const carte = carteDeplacee;
        const origine = carte?.closest('.kanban-col');
        if (!carte || origine === col) {
            return;
        }
        const liste = col.querySelector('.kanban-liste');
        liste.insertBefore(carte, liste.querySelector('.kanban-vide'));
        carte.classList.add('envoi');
        mettreAJourCompteurs();
        try {
            await envoyer('actions.php', { do: 'statut', id: carte.dataset.id, statut: col.dataset.statut });
        } catch (erreur) {
            const listeOrigine = origine.querySelector('.kanban-liste');
            listeOrigine.insertBefore(carte, listeOrigine.querySelector('.kanban-vide'));
            mettreAJourCompteurs();
            alert('Le statut n\'a pas pu être changé : ' + erreur.message);
        } finally {
            carte.classList.remove('envoi');
        }
    });
});

/* ---------- « Quelqu'un a modifié quelque chose » ---------- */

const bandeau = document.getElementById('bandeau-nouveautes');
const depart = Number(document.body.dataset.dernierEvenement || 0);

if (bandeau && depart > 0) {
    bandeau.querySelector('.js-recharger').addEventListener('click', (evt) => {
        evt.preventDefault();
        location.reload();
    });

    const verifier = async () => {
        if (document.hidden) {
            return;
        }
        try {
            const reponse = await fetch('nouveautes.php?depuis=' + depart, { headers: { 'X-Requested-With': 'fetch' } });
            const json = await reponse.json();
            if (json.ok && json.donnees.nombre > 0) {
                const qui = json.donnees.auteurs.join(', ');
                const n = json.donnees.nombre;
                bandeau.querySelector('span').textContent = `${qui} ${json.donnees.auteurs.length > 1 ? 'ont' : 'a'} fait ${n} modification${n > 1 ? 's' : ''} depuis que tu as ouvert cette page.`;
                bandeau.hidden = false;
            }
        } catch {
            // hors ligne ou serveur arrêté : on réessaiera au prochain tour
        }
    };
    setInterval(verifier, 20000);
    document.addEventListener('visibilitychange', verifier);
}
