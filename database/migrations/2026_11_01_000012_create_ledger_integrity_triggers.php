<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const TRIGGERS = [
        'trg_ji_bi',
        'trg_ji_bu',
        'trg_ji_bd',
        'trg_je_bi',
        'trg_je_bu',
        'trg_je_bd',
        'trg_coa_bi',
        'trg_coa_bu',
        'trg_branches_bu',
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach (self::TRIGGERS as $trigger) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$trigger}");
        }

        DB::unprepared(<<<'SQL'
CREATE TRIGGER trg_ji_bi BEFORE INSERT ON journal_items FOR EACH ROW
BEGIN
  IF (SELECT status FROM journal_entries WHERE id = NEW.journal_entry_id) <> 'draft' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'journal_items: jurnal sudah posted, gunakan reversal';
  END IF;
  IF (SELECT is_postable FROM chart_of_accounts WHERE id = NEW.account_id) = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'journal_items: akun header tidak dapat diposting';
  END IF;
  IF NOT (NEW.entity_id <=> (SELECT entity_id FROM journal_entries WHERE id = NEW.journal_entry_id))
     OR NOT (NEW.entity_id <=> (SELECT entity_id FROM chart_of_accounts WHERE id = NEW.account_id))
     OR NOT (NEW.entity_id <=> (SELECT entity_id FROM branches WHERE id = NEW.branch_id)) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'journal_items: entitas tidak konsisten (dilarang mencampur entitas)';
  END IF;
END
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER trg_ji_bu BEFORE UPDATE ON journal_items FOR EACH ROW
BEGIN
  IF (SELECT status FROM journal_entries WHERE id = OLD.journal_entry_id) <> 'draft'
     OR (SELECT status FROM journal_entries WHERE id = NEW.journal_entry_id) <> 'draft' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'journal_items: immutable setelah posted';
  END IF;
  IF (SELECT is_postable FROM chart_of_accounts WHERE id = NEW.account_id) = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'journal_items: akun header tidak dapat diposting';
  END IF;
  IF NOT (NEW.entity_id <=> (SELECT entity_id FROM journal_entries WHERE id = NEW.journal_entry_id))
     OR NOT (NEW.entity_id <=> (SELECT entity_id FROM chart_of_accounts WHERE id = NEW.account_id))
     OR NOT (NEW.entity_id <=> (SELECT entity_id FROM branches WHERE id = NEW.branch_id)) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'journal_items: entitas tidak konsisten (dilarang mencampur entitas)';
  END IF;
END
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER trg_ji_bd BEFORE DELETE ON journal_items FOR EACH ROW
BEGIN
  IF (SELECT status FROM journal_entries WHERE id = OLD.journal_entry_id) <> 'draft' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'journal_items: immutable setelah posted';
  END IF;
END
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER trg_je_bi BEFORE INSERT ON journal_entries FOR EACH ROW
BEGIN
  IF NEW.status <> 'draft' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'journal_entries: jurnal baru wajib berstatus draft';
  END IF;
  IF NOT (NEW.entity_id <=> (SELECT entity_id FROM branches WHERE id = NEW.branch_id)) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'journal_entries: branch bukan milik entity';
  END IF;
END
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER trg_je_bu BEFORE UPDATE ON journal_entries FOR EACH ROW
BEGIN
  DECLARE v_debit DECIMAL(18,2);
  DECLARE v_credit DECIMAL(18,2);
  DECLARE v_lines INT;
  DECLARE v_period_status VARCHAR(20);

  IF OLD.status = 'draft' THEN
    IF NEW.status NOT IN ('draft', 'posted') THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'journal_entries: draft hanya boleh menjadi posted';
    END IF;
    IF NOT (NEW.entity_id <=> (SELECT entity_id FROM branches WHERE id = NEW.branch_id)) THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'journal_entries: branch bukan milik entity';
    END IF;
    IF NEW.status = 'posted' THEN
      SELECT COALESCE(SUM(debit), 0), COALESCE(SUM(credit), 0), COUNT(*)
        INTO v_debit, v_credit, v_lines
        FROM journal_items WHERE journal_entry_id = NEW.id;
      IF v_lines < 2 OR v_debit <> v_credit OR v_debit = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'journal_entries: jurnal tidak seimbang / kosong';
      END IF;
      SET v_period_status = (
        SELECT status FROM accounting_periods
        WHERE entity_id <=> NEW.entity_id
          AND (branch_id = NEW.branch_id OR branch_id IS NULL)
          AND NEW.entry_date BETWEEN period_start AND period_end
        ORDER BY branch_id IS NULL
        LIMIT 1
      );
      IF v_period_status = 'closed' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'journal_entries: periode sudah ditutup';
      END IF;
      SET NEW.total_debit = v_debit, NEW.total_credit = v_credit, NEW.posted_at = COALESCE(NEW.posted_at, NOW());
    END IF;
  ELSEIF OLD.status = 'posted' THEN
    IF NOT (NEW.status = 'reversed'
            AND OLD.reversed_by_id IS NULL AND NEW.reversed_by_id IS NOT NULL
            AND NEW.entry_date = OLD.entry_date
            AND NEW.branch_id = OLD.branch_id
            AND NEW.entity_id <=> OLD.entity_id
            AND NEW.total_debit = OLD.total_debit
            AND NEW.total_credit = OLD.total_credit) THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'journal_entries: jurnal posted bersifat immutable';
    END IF;
  ELSE
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'journal_entries: jurnal reversed bersifat final';
  END IF;
END
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER trg_je_bd BEFORE DELETE ON journal_entries FOR EACH ROW
BEGIN
  IF OLD.status <> 'draft' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'journal_entries: tidak boleh dihapus, gunakan reversal';
  END IF;
END
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER trg_coa_bi BEFORE INSERT ON chart_of_accounts FOR EACH ROW
BEGIN
  IF NEW.branch_id IS NOT NULL
     AND NOT (NEW.entity_id <=> (SELECT entity_id FROM branches WHERE id = NEW.branch_id)) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'chart_of_accounts: branch_id harus milik entity yang sama';
  END IF;
END
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER trg_coa_bu BEFORE UPDATE ON chart_of_accounts FOR EACH ROW
BEGIN
  IF NEW.branch_id IS NOT NULL
     AND NOT (NEW.entity_id <=> (SELECT entity_id FROM branches WHERE id = NEW.branch_id)) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'chart_of_accounts: branch_id harus milik entity yang sama';
  END IF;
END
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER trg_branches_bu BEFORE UPDATE ON branches FOR EACH ROW
BEGIN
  IF NOT (NEW.entity_id <=> OLD.entity_id)
     AND EXISTS (SELECT 1 FROM journal_entries WHERE branch_id = OLD.id AND status IN ('posted', 'reversed')) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'branches: entity_id tidak boleh diubah, cabang sudah punya jurnal posted';
  END IF;
END
SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach (self::TRIGGERS as $trigger) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$trigger}");
        }
    }
};
