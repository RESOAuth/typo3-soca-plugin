CREATE TABLE tx_soca_ticket (
  state_hash varchar(64) DEFAULT '' NOT NULL,
  browser_hash varchar(64) DEFAULT '' NOT NULL,
  revision varchar(64) DEFAULT '' NOT NULL,
  expires int unsigned DEFAULT 0 NOT NULL,
  PRIMARY KEY (state_hash),
  KEY expiry (expires)
);
CREATE TABLE tx_soca_link (
  identity_hash varchar(64) DEFAULT '' NOT NULL,
  user_id int unsigned DEFAULT 0 NOT NULL,
  created_at int unsigned DEFAULT 0 NOT NULL,
  PRIMARY KEY (identity_hash)
);
