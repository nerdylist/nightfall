-- THE DEAD LAST — migration 023: enemy xp_value dial (Boss/Nancy 2026-07-29).
-- XP awarded for killing a character. Goes with the wave dials (016/021): a
-- character-level default, and optionally per-band (inside hp_bands) so a
-- deep-wave Franky can be worth more than an early one. The game reads it
-- defensively (absent -> 50), so this is non-breaking.
-- See the game repo: docs/ROADMAP/survivor-progression-handoff.md §4.
--
-- Sketch defaults: shambler 50 · midgame 80 · heavy 400 · machine 600.

ALTER TABLE characters ADD COLUMN xp_value INTEGER;   -- null -> game default 50
