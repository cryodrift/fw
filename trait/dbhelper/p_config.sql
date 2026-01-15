-- Increase the cache size
PRAGMA cache_size = -40000;
PRAGMA mmap_size = 300000000;
PRAGMA synchronous = EXTRA;
PRAGMA temp_store = MEMORY;
PRAGMA journal_mode = MEMORY;
PRAGMA encoding = 'UTF-8';
PRAGMA foreign_keys = ON;
