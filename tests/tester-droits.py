# SPDX-FileCopyrightText: 2026 Flavien Auvray
# SPDX-License-Identifier: AGPL-3.0-or-later
"""Test du cloisonnement de l'appli Familles, compte par compte, par l'API.

    python tests/tester-droits.py [http://localhost:8150]

Crée 2 familles fictives, ouvre l'une à Paul (psychologue) et l'autre à Marie (médecin),
puis vérifie que chacun ne voit QUE ce qu'il doit voir. Supprime tout à la fin.
Comptes : ceux de la démo Docker (claire, paul, marie ; mot de passe dans FAMILLES_MDP,
par défaut « familles2026 »). Lancé automatiquement par scripts/tester.sh.
"""
import base64
import os
import json
import sys
import urllib.error
import urllib.request

BASE = (sys.argv[1] if len(sys.argv) > 1 else "http://localhost:8150") + "/index.php/apps/suivi_familles/api"
MDP = os.environ.get("FAMILLES_MDP", "familles2026")
echecs = 0


def appel(qui, methode, chemin, corps=None):
    """Renvoie (code HTTP, JSON). Basic auth + OCS-APIRequest = pas besoin de jeton CSRF."""
    donnees = json.dumps(corps, ensure_ascii=True).encode("utf-8") if corps is not None else None
    req = urllib.request.Request(BASE + chemin, data=donnees, method=methode)
    req.add_header("Authorization", "Basic " + base64.b64encode(f"{qui}:{MDP}".encode()).decode())
    req.add_header("OCS-APIRequest", "true")
    req.add_header("Content-Type", "application/json")
    try:
        with urllib.request.urlopen(req) as r:
            return r.status, json.loads(r.read() or b"null")
    except urllib.error.HTTPError as e:
        texte = e.read()
        try:
            return e.code, json.loads(texte)
        except ValueError:
            return e.code, texte[:200]


def verifier(libelle, condition, detail=""):
    global echecs
    if condition:
        print(f"  OK   {libelle}")
    else:
        echecs += 1
        print(f"  ÉCHEC {libelle} {detail}")


print("Préparation (Claire, direction)")
_, martin = appel("claire", "POST", "/familles", {"nom": "Famille Martin (test)", "telephone": "06 00 00 00 01", "dateEntree": "2026-09-01"})
_, benali = appel("claire", "POST", "/familles", {"nom": "Famille Benali (test)"})
A, B = martin["id"], benali["id"]
_, lea = appel("claire", "POST", f"/familles/{A}/membres", {"prenom": "Léa", "lien": "Enfant", "dateNaissance": "2015-04-12", "medecinTraitant": "Dr Morel", "psychologue": "Paul Lefèvre"})
appel("claire", "POST", f"/familles/{A}/acces", {"userId": "paul", "fonction": "Psychologue", "peutModifier": False})
appel("claire", "POST", f"/familles/{B}/acces", {"userId": "marie", "fonction": "Médecin", "peutModifier": True})

print("Paul (psychologue, famille Martin seulement, lecture + suivis)")
c, r = appel("paul", "GET", "/familles")
# On ne regarde que les familles créées par ce test (les familles de démo peuvent exister).
noms = [f["nom"] for f in r["familles"] if f["nom"].endswith("(test)")]
verifier("ne voit que la famille Martin dans sa liste", noms == ["Famille Martin (test)"], noms)
verifier("n'est pas direction", r["direction"] is False)
c, r = appel("paul", "GET", f"/familles/{A}")
verifier("ouvre la fiche Martin", c == 200 and r["membres"][0]["prenom"] == "Léa", c)
c, _ = appel("paul", "GET", f"/familles/{B}")
verifier("fiche Benali → 404 (même réponse que si elle n'existait pas)", c == 404, c)
c, _ = appel("paul", "PUT", f"/familles/{A}", {"telephone": "07 99 99 99 99"})
verifier("ne peut pas modifier la fiche Martin → 403", c == 403, c)
c, _ = appel("paul", "PUT", f"/membres/{lea['id']}", {"remarques": "x"})
verifier("ne peut pas modifier un membre → 403", c == 403, c)
c, note_paul = appel("paul", "POST", f"/familles/{A}/suivis", {"texte": "Séance avec Léa, anxiété scolaire.", "membreId": lea["id"], "prive": True})
verifier("écrit une note privée", c == 200 and note_paul["prive"] is True, c)
c, note_pub = appel("paul", "POST", f"/familles/{A}/suivis", {"texte": "RDV de suivi fixé au 20/11.", "dateSuivi": "2026-11-08"})
verifier("écrit une note d'équipe", c == 200, c)
c, _ = appel("paul", "POST", f"/familles/{B}/suivis", {"texte": "intrusion"})
verifier("ne peut pas écrire chez Benali → 404", c == 404, c)
c, _ = appel("paul", "POST", f"/familles/{A}/acces", {"userId": "marie", "fonction": "x"})
verifier("ne peut pas ouvrir l'accès à quelqu'un → 403", c == 403, c)
c, _ = appel("paul", "GET", "/journal")
verifier("pas d'accès au journal → 403", c == 403, c)
c, _ = appel("paul", "DELETE", f"/familles/{A}")
verifier("ne peut pas supprimer la famille → 403", c == 403, c)
c, _ = appel("paul", "GET", "/utilisateurs?q=mar")
verifier("ne peut pas chercher dans les comptes → 403", c == 403, c)

print("Marie (médecin, famille Benali seulement, peut modifier)")
c, r = appel("marie", "GET", "/familles")
verifier("ne voit que la famille Benali", [f["nom"] for f in r["familles"] if f["nom"].endswith("(test)")] == ["Famille Benali (test)"], [f["nom"] for f in r["familles"]])
c, _ = appel("marie", "GET", f"/familles/{A}")
verifier("fiche Martin → 404", c == 404, c)
c, _ = appel("marie", "PUT", f"/familles/{B}", {"adresse": "3 rue des Lilas"})
verifier("modifie la fiche Benali", c == 200, c)
c, _ = appel("marie", "PUT", f"/familles/{B}/statut", {"statut": "clos"})
verifier("ne peut pas clore le dossier (direction) → 403", c == 403, c)
c, _ = appel("marie", "POST", f"/familles/{B}/membres", {"prenom": "Karim", "lien": "Parent"})
verifier("ajoute un membre chez Benali", c == 200, c)
c, _ = appel("marie", "PUT", f"/suivis/{note_pub['id']}", {"texte": "piratage"})
verifier("ne peut pas toucher une note de la famille Martin → 404", c == 404, c)

c, _ = appel("marie", "POST", f"/familles/{B}/objectifs", {"titre": "Trouver un médecin traitant"})
verifier("ajoute un objectif chez Benali (peut modifier)", c == 200, c)
c, _ = appel("marie", "PUT", f"/familles/{B}/referent", {"userId": "marie"})
verifier("ne peut pas se nommer référente (direction) → 403", c == 403, c)

print("Actions, objectifs, référent")
c, act = appel("paul", "POST", f"/familles/{A}/actions", {"titre": "Appeler l'école (test)", "echeance": "2026-11-20", "responsable": "paul"})
verifier("Paul ajoute une action chez Martin", c == 200, c)
c, _ = appel("paul", "POST", f"/familles/{A}/actions", {"titre": "x", "responsable": "marie"})
verifier("ne peut pas confier une action à quelqu'un hors de l'équipe → 400", c == 400, c)
c, _ = appel("paul", "POST", f"/familles/{B}/actions", {"titre": "intrusion"})
verifier("ne peut pas ajouter d'action chez Benali → 404", c == 404, c)
c, _ = appel("paul", "POST", f"/familles/{A}/actions", {"titre": "RDV sans date", "genre": "rdv"})
verifier("refuse un rendez-vous sans date → 400", c == 400, c)
c, r = appel("paul", "PUT", f"/actions/{act['id']}", {"fait": True})
verifier("Paul coche son action", c == 200 and r["fait"] is True, c)
c, _ = appel("paul", "POST", f"/familles/{A}/objectifs", {"titre": "x"})
verifier("Paul (lecture) ne peut pas ajouter d'objectif → 403", c == 403, c)
c, _ = appel("marie", "PUT", f"/actions/{act['id']}", {"fait": False})
verifier("Marie ne touche pas aux actions de Martin → 404", c == 404, c)
c, r = appel("claire", "PUT", f"/familles/{A}/referent", {"userId": "paul"})
verifier("Claire nomme Paul référent", c == 200 and r["referent"] == "paul", c)
c, _ = appel("claire", "PUT", f"/familles/{A}/referent", {"userId": "marie"})
verifier("refuse un référent hors de l'équipe → 400", c == 400, c)
c, r = appel("paul", "GET", "/accueil")
noms_acc = [f["nom"] for f in r["mesFamilles"] if f["nom"].endswith("(test)")]
verifier("accueil de Paul : seulement Martin", noms_acc == ["Famille Martin (test)"], noms_acc)
verifier("accueil de Paul : pas de points d'attention direction", "sansEquipe" not in r)

print("Marie rejoint aussi la famille Martin")
appel("claire", "POST", f"/familles/{A}/acces", {"userId": "marie", "fonction": "Médecin"})
c, r = appel("marie", "GET", f"/familles/{A}")
textes = [s["texte"] for s in r["suivis"]]
verifier("voit la note d'équipe de Paul", "RDV de suivi fixé au 20/11." in textes, textes)
verifier("ne voit PAS la note privée de Paul", all("anxiété" not in t for t in textes), textes)
c, _ = appel("marie", "PUT", f"/suivis/{note_pub['id']}", {"texte": "modif"})
verifier("ne peut pas modifier la note de Paul → 403", c == 403, c)

print("Claire (direction)")
c, r = appel("claire", "GET", "/familles")
verifier("voit les 2 familles avec leur équipe", {"Famille Martin (test)", "Famille Benali (test)"} <= {f["nom"] for f in r["familles"]} and r["direction"])
c, r = appel("claire", "GET", f"/familles/{A}")
verifier("voit la note privée de Paul", any("anxiété" in s["texte"] for s in r["suivis"]))
c, r = appel("claire", "GET", f"/journal?famille={A}")
actions = {(j["userId"], j["action"]) for j in r}
verifier("journal : consultation de Paul tracée", ("paul", "consultation") in actions, actions)
verifier("journal : accès donné à Paul tracé", ("claire", "acces_ajout") in actions)
c, r = appel("claire", "GET", "/utilisateurs?q=mar")
verifier("recherche de comptes", any(u["id"] == "marie" for u in r), r)
c, _ = appel("claire", "POST", "/familles", {"nom": "  "})
verifier("refuse une famille sans nom → 400", c == 400, c)
c, _ = appel("claire", "POST", f"/familles/{A}/membres", {"prenom": "Zoé", "dateNaissance": "2015-13-45"})
verifier("refuse une date impossible → 400", c == 400, c)

c, r = appel("claire", "PUT", f"/familles/{A}/statut", {"statut": "clos", "motif": "Objectifs atteints"})
verifier("Claire clôt Martin avec un motif", c == 200 and r["statut"] == "clos" and r["motifFin"] == "Objectifs atteints", c)
c, r = appel("claire", "PUT", f"/familles/{A}/statut", {"statut": "actif"})
verifier("Claire rouvre Martin", c == 200 and r["statut"] == "actif" and r["dateFin"] is None, c)
c, _ = appel("claire", "POST", f"/familles/{A}/impression")
c2, r = appel("claire", "GET", f"/journal?famille={A}")
verifier("impression tracée au journal", c == 200 and any(j["action"] == "impression" for j in r), c)

print("Nettoyage")
for fid in (A, B):
    c, _ = appel("claire", "DELETE", f"/familles/{fid}")
    verifier(f"suppression famille {fid}", c == 200, c)
c, _ = appel("paul", "GET", "/familles")
print()
print("TOUT EST BON" if echecs == 0 else f"{echecs} ÉCHEC(S)")
sys.exit(1 if echecs else 0)
