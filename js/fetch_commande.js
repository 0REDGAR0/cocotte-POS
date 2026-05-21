function chargerNumeroCommande() {
  fetch("php/get_next_commande_id.php")
    .then(res => res.json())
    .then(data => {
      const id = data.next_id;
      const titre = document.querySelector(".commande-gauche h2 span:first-child");
      if (id && titre) {
        titre.textContent = `SAISIE DE COMMANDE : ${id}`;
      }
    });
}


let messageCommande = "";

function escapeHtml(value) {
  return String(value ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#39;");
}

function normalizeKey(value) {
  return String(value ?? "")
    .trim()
    .toLowerCase()
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/\s+/g, "_");
}

const CATEGORY_META = {
  bar: { label: "Bar", className: "category-bar" },
  cuisine: { label: "Cuisine", className: "category-cuisine" },
  snack: { label: "Snack", className: "category-snack" }
};

const STATUS_META = {
  en_cours: { label: "En cours", className: "status-en-cours" },
  pret: { label: "Pret", className: "status-pret" },
  termine: { label: "Termine", className: "status-termine" },
  terminee: { label: "Termine", className: "status-termine" },
  archive: { label: "Archive", className: "status-archive" },
  archivee: { label: "Archive", className: "status-archive" }
};

function renderSymbolBadge(kind, rawValue) {
  const normalized = normalizeKey(rawValue);
  const metaMap = kind === "status" ? STATUS_META : CATEGORY_META;
  const meta = metaMap[normalized];
  const fallbackLabel = rawValue
    ? String(rawValue).replace(/_/g, " ")
    : kind === "status" ? "Inconnu" : "Autre";
  const label = meta ? meta.label : fallbackLabel;
  const className = meta ? meta.className : `${kind}-unknown`;
  const prefix = kind === "status" ? "Statut" : "Categorie";

  return `
    <span class="cell-symbol ${className}" title="${escapeHtml(prefix)} ${escapeHtml(label)}" aria-label="${escapeHtml(prefix)} ${escapeHtml(label)}">
      <span class="cell-symbol-icon" aria-hidden="true"></span>
      <span class="cell-symbol-label">${escapeHtml(label)}</span>
    </span>
  `;
}

document.addEventListener("DOMContentLoaded", () => {
  fetch("php/get_articles.php")
    .then(res => res.json())
    .then(data => {
//      console.log("Articles récupérés :", data);  // AJOUTE CETTE LIGNE
      data.forEach(article => {
        const btn = document.createElement("button");
        btn.textContent = article.nom;
        btn.dataset.id = article.id;
        btn.dataset.nom = article.nom;
        btn.dataset.prix = article.prix;
        
        if (article.bar == 1) {
          btn.dataset.categorie = "bar";
          document.getElementById("col-bar").appendChild(btn);
        }
        if (article.cuisine == 1) {
          btn.dataset.categorie = "cuisine";
          document.getElementById("col-cuisine").appendChild(btn);
        }
        if (article.snack == 1) {
          btn.dataset.categorie = "snack";
          document.getElementById("col-snack").appendChild(btn);
        }
        
        // Action : ajouter à la commande quand on clique
        btn.addEventListener("click", () => {
          addArticleToCommande(btn.dataset);
        });
      });
    })
    .catch(err => {
      console.error("Erreur chargement articles :", err);
    });
});

function addArticleToCommande(data) {
  const nom = data.nom.toLowerCase();
  const tbody = document.getElementById("commande-lignes");
  const categoryBadge = renderSymbolBadge("category", data.categorie);

  // Détection Glace X boule(s)
  const glaceMatch = nom.match(/glace (\d) boule/);
  const nombreBoules = glaceMatch ? parseInt(glaceMatch[1]) : 0;

  const articleTr = document.createElement("tr");
  articleTr.classList.add("ligne-article");
  articleTr.setAttribute("data-id", data.id);
  articleTr.innerHTML = `
    <td>1</td>
    <td>${escapeHtml(data.nom)}</td>
    <td>${categoryBadge}</td>
    <td>${parseFloat(data.prix).toFixed(2)}</td>
  `;

  tbody.appendChild(articleTr);

  if (!nombreBoules) {
    updatePrixTotal();
    scrollCommandeBas();
    return;
  }

  // Si c’est une glace → lancer le popup
  ouvrirPopupParfums(nombreBoules, data.categorie, articleTr);
}

const parfumsDisponibles = [
  "Café", "Chocolat", "Vanille", "Citron", "Fruits rouges", "Caramel", "Mangue"
];

function ouvrirPopupParfums(nb, categorie, articleTr) {
  const popup = document.getElementById("popup-parfums");
  const conteneur = document.getElementById("popup-boules");
  const categoryBadge = renderSymbolBadge("category", categorie);
  conteneur.innerHTML = "";
  let selections = [];

  const annulerPopup = () => {
    popup.style.display = "none";
    articleTr.remove();
  };

  document.getElementById("popup-annuler").onclick = annulerPopup;

  parfumsDisponibles.forEach(parfum => {
    const btn = document.createElement("button");
    btn.textContent = parfum;
    btn.onclick = () => {
      selections.push(parfum);

      if (selections.length >= nb) {
        const tr = document.createElement("tr");
        tr.style.opacity = "0.6";
        tr.classList.add("msg-ligne");

        tr.innerHTML = `
          <td>1</td>
          <td>Message : ${escapeHtml(selections.join(", "))}</td>
          <td>${categoryBadge}</td>
          <td>0.00</td>
        `;
        articleTr.parentNode.insertBefore(tr, articleTr.nextSibling);

        popup.style.display = "none";
        updatePrixTotal();
        scrollCommandeBas();
      }
    };
    conteneur.appendChild(btn);
  });

  popup.style.display = "flex";
}

function scrollCommandeBas() {
  const scrollableDiv = document.querySelector(".commande-corps");
  scrollableDiv.scrollTop = scrollableDiv.scrollHeight;
}



function updatePrixTotal() {
  let total = 0;
  document.querySelectorAll("#commande-lignes tr").forEach(tr => {
    const prix = parseFloat(tr.children[3].textContent);
    if (!isNaN(prix)) total += prix;
  });
  document.getElementById("prix-total").textContent = total.toFixed(2);
}

document.getElementById("msgBtn").addEventListener("click", () => {
  const texte = prompt("Quel est le message ?");
  if (!texte) return;

  const lignes = document.querySelectorAll("#commande-lignes tr");
  if (lignes.length === 0) {
    alert("Vous devez d'abord ajouter un article pour définir la catégorie.");
    return;
  }

  const derniereLigne = lignes[lignes.length - 1];
  const categorie = derniereLigne.children[2].textContent.toLowerCase();

  const tbody = document.getElementById("commande-lignes");
  const categoryBadge = renderSymbolBadge("category", categorie);
  const tr = document.createElement("tr");
  tr.style.opacity = "0.6";
  tr.innerHTML = `
    <td>1</td>
    <td>Message : ${escapeHtml(texte)}</td>
    <td>${categoryBadge}</td>
    <td>0.00</td>
  `;
  tr.classList.add("msg-ligne");

  tbody.appendChild(tr);
  updatePrixTotal();

  const scrollableDiv = document.querySelector(".commande-corps");
  scrollableDiv.scrollTop = scrollableDiv.scrollHeight;
});

document.getElementById("annulBtn").addEventListener("click", () => {
  const lignes = document.querySelectorAll("#commande-lignes tr");
  if (lignes.length > 0) {
    lignes[lignes.length - 1].remove();
    updatePrixTotal();
  }
});

document.getElementById("validerBtn").addEventListener("click", () => {
  const lignes = [...document.querySelectorAll("#commande-lignes tr")];
  const regrouped = [];
  const regroupMap = {};

  for (let i = 0; i < lignes.length; i++) {
    const tr = lignes[i];
    const cells = tr.querySelectorAll("td");

    const quantite = parseInt(cells[0].textContent);
    const nom = cells[1].textContent;
    const categorie = cells[2].textContent.toLowerCase();
    const prix = parseFloat(cells[3].textContent);
    const id = parseInt(tr.getAttribute("data-id")) || 0;

    const isMessage = nom.toLowerCase().startsWith("message");

    if (isMessage) {
      regrouped.push({ id, nom, prix, quantite, categorie });
      continue;
    }

    const trSuivante = lignes[i + 1];
    const messageLie =
      trSuivante &&
      trSuivante.querySelectorAll("td")[1].textContent.toLowerCase().startsWith("message");

    if (messageLie) {
      regrouped.push({ id, nom, prix, quantite, categorie });
      continue;
    }

    const key = `${id}-${categorie}-${prix}`;
    if (!regroupMap[key]) {
      regroupMap[key] = { id, nom, prix, quantite, categorie };
      regrouped.push(regroupMap[key]);
    } else {
      regroupMap[key].quantite += quantite;
    }
  }

  const payload = {
    message: "",
    articles: regrouped
  };

  fetch("php/ajouter_commande.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(payload)
  })
    .then(res => res.json())
    .then(resp => {
      if (resp.success) {
        document.getElementById("commande-lignes").innerHTML = "";
        document.getElementById("prix-total").textContent = "0.00";
        chargerNumeroCommande();
      } else {
        alert("Erreur : " + resp.message);
      }
    })
    .catch(err => {
      console.error("Erreur réseau :", err);
    });
});

function chargerCommandesEnCours() {
  fetch("php/get_commandes.php")
    .then(res => res.json())
    .then(commandes => {
      commandes = commandes.filter(cmd => cmd.statut === "en_cours");
      const container = document.getElementById("commandes-en-cours");
      container.innerHTML = "";

      commandes.forEach(cmd => {
        const bloc = document.createElement("div");
        bloc.classList.add("commande-bloc");

        // Statut global
        const statuts = cmd.articles.map(a => a.statut);
        const allEnCours = statuts.every(s => s === "en_cours");
        const allPrets = statuts.every(s => s === "pret" || s === "terminé");

        let classeStatut = "commande-jaune"; // Par défaut = mix
        if (allEnCours) classeStatut = "commande-orange";
        else if (allPrets) classeStatut = "commande-verte";


        bloc.classList.add(classeStatut);

        // En-tête
        const header = document.createElement("div");
        header.className = "commande-header";
        header.innerHTML = `<span style="flex:1; text-align:left;">COMMANDE NUMÉRO: ${cmd.id}</span>`;

        const timer = document.createElement("span");
        timer.style.marginRight = "10px";
        header.appendChild(timer);

        // Lancer le timer dynamique
        calculerEtAfficherTemps(cmd.date, timer);

        // Bouton FERMER (seulement si tous les articles sont prêts)
        if (allPrets) {
          const btnFermer = document.createElement("button");
          btnFermer.className = "fermer-btn";
          btnFermer.textContent = "FERMER";
          btnFermer.addEventListener("click", () => {
            btnFermer.disabled = true;
            fermerCommande(cmd.id);
          });
          header.appendChild(btnFermer);
        }

        bloc.appendChild(header);

        // En-têtes colonnes
        const headRow = document.createElement("div");
        headRow.className = "commande-header-row";
        headRow.innerHTML = `<div>NB</div><div>ARTICLE</div><div>CATEGORIE</div><div>STATUT</div>`;
        bloc.appendChild(headRow);

        // Contenu
        cmd.articles.forEach(art => {
        const ligne = document.createElement("div");
        ligne.className = "commande-ligne";

        const nom = art.nom || "";
        const categorie = art.categorie ? art.categorie.toUpperCase() : "-";
        const statut = art.statut ? art.statut.toUpperCase() : "-";
        const quantite = art.quantite || 1;

        if (nom.toLowerCase().startsWith("message")) {
          ligne.classList.add("ligne-message");
        }

        ligne.innerHTML = `
          <span>${quantite}</span>
          <span>${nom}</span>
          <span>${renderSymbolBadge("category", categorie)}</span>
          <span>${renderSymbolBadge("status", statut)}</span>
        `;
          bloc.appendChild(ligne);
        });

        container.appendChild(bloc);
      });
    })
    .catch(err => console.error("Erreur chargement commandes :", err));
}

setInterval(chargerCommandesEnCours, 3000);
chargerCommandesEnCours();

function fermerCommande(commande_id) {
  fetch("php/archiver.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ commande_id: commande_id })
  })
    .then(res => res.json())
    .then(resp => {
      if (resp.success) {
        chargerCommandesEnCours();
      } else {
        alert(resp.message || "Erreur fermeture commande");
      }
    })
    .catch(err => {
      alert("Erreur réseau fermeture");
      console.error(err);
    });
}

document.getElementById("commande-lignes").addEventListener("click", e => {
  const tr = e.target.closest("tr");
  if (!tr || !tr.classList.contains("ligne-article")) return;

  const nomArticle = tr.children[1].textContent;

  const choix = confirm(`Que souhaitez-vous faire pour "${nomArticle}" ?\n\nOK : Ajouter un message associé\nAnnuler : Supprimer la ligne`);

  if (choix) {
    // Ajout de message
    const messageTexte = prompt("Message associé à cet article :");
    if (messageTexte) {
      const newTr = document.createElement("tr");
      const categoryText = tr.children[2].textContent;
      newTr.innerHTML = `
        <td>1</td>
        <td><em style="color: #666;">Message : ${escapeHtml(messageTexte)}</em></td>
        <td>${renderSymbolBadge("category", categoryText)}</td>
        <td>0.00</td>
      `;
      // Insertion juste après la ligne cliquée
      tr.parentNode.insertBefore(newTr, tr.nextSibling);
    }
  } else {
    // Suppression de la ligne
    tr.remove();
  }
});

function calculerEtAfficherTemps(dateISO, spanCible) {
  const created = new Date(dateISO);

  function update() {
    const now = new Date();
    const elapsed = Math.floor((now - created) / 1000);
    const minutes = Math.floor(elapsed / 60).toString().padStart(2, '0');
    const seconds = (elapsed % 60).toString().padStart(2, '0');
    spanCible.textContent = `⏱ ${minutes}:${seconds}`;
  }

  update();
  return setInterval(update, 1000);
}
