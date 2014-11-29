-- PRAGMA synchronous = OFF;
-- PRAGMA journal_mode = MEMORY;
-- BEGIN TRANSACTION;

CREATE TABLE IF NOT EXISTS `feeds` (
  `id` INTEGER UNIQUE PRIMARY KEY AUTOINCREMENT ,
  `url` varchar(255) NOT NULL,
  `https` tinyint(1) DEFAULT NULL,
  `link` varchar(255) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `error` varchar(255) DEFAULT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  `fetch_interval` int(2) NOT NULL DEFAULT '15',
  `fetched_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE (`url`)
  );

CREATE TABLE IF NOT EXISTS `entries` (
  `id` INTEGER UNIQUE PRIMARY KEY AUTOINCREMENT ,
  `date` timestamp NULL DEFAULT NULL,
  `permalink` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text DEFAULT NULL,
  `categories` varchar(255) DEFAULT NULL,
  `hash` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `feed_id` int(10)  NOT NULL,
   FOREIGN KEY (`feed_id`) REFERENCES `feeds` (`id`) ON DELETE CASCADE
  );

-- CREATE INDEX "_" ON "" (`feed_id`);
-- CREATE INDEX "_" ON "" (`url`);
-- CREATE INDEX "_" ON "" (`feed_id`);
-- END TRANSACTION;
