PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS app_meta (
  meta_key TEXT PRIMARY KEY,
  meta_value TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS articles (
  id INTEGER PRIMARY KEY,
  nom TEXT NOT NULL,
  prix NUMERIC NOT NULL,
  bar INTEGER NOT NULL DEFAULT 0 CHECK (bar IN (0, 1)),
  cuisine INTEGER NOT NULL DEFAULT 0 CHECK (cuisine IN (0, 1)),
  snack INTEGER NOT NULL DEFAULT 0 CHECK (snack IN (0, 1))
);

CREATE TABLE IF NOT EXISTS commandes (
  id INTEGER PRIMARY KEY,
  message TEXT DEFAULT '',
  statut TEXT NOT NULL DEFAULT 'en_cours' CHECK (statut IN ('en_cours', 'terminee')),
  date TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  closed_at TEXT NULL
);

CREATE TABLE IF NOT EXISTS commande_articles (
  id INTEGER PRIMARY KEY,
  commande_id INTEGER NOT NULL,
  article_id INTEGER NULL,
  quantite INTEGER NOT NULL DEFAULT 1,
  categorie TEXT NOT NULL CHECK (categorie IN ('bar', 'cuisine', 'snack')),
  statut TEXT NOT NULL DEFAULT 'en_cours' CHECK (statut IN ('en_cours', 'pret')),
  prix NUMERIC NOT NULL DEFAULT 0,
  nom TEXT NULL,
  FOREIGN KEY (commande_id) REFERENCES commandes(id) ON DELETE CASCADE,
  FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_articles_nom
  ON articles(nom);

CREATE INDEX IF NOT EXISTS idx_commandes_statut
  ON commandes(statut);

CREATE INDEX IF NOT EXISTS idx_commande_articles_commande
  ON commande_articles(commande_id);

CREATE INDEX IF NOT EXISTS idx_commande_articles_categorie_statut
  ON commande_articles(categorie, statut);

INSERT OR REPLACE INTO app_meta (meta_key, meta_value)
VALUES ('schema_version', '1');
