<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Baseline schema. Generated from the entity mappings; before this the schema was managed
 * with doctrine:schema:update. Existing installations return normally without replaying the
 * baseline, allowing Doctrine to record the version as executed.
 */
final class Version20260903062452 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the baseline application schema.';
    }

    public function up(Schema $schema): void
    {
        if ($this->connection->executeQuery("SELECT to_regclass('classycash.setting')")->fetchOne() !== null) {
            $this->write('Baseline schema already present; recording migration without replaying it.');

            return;
        }

        $this->addSql('CREATE SCHEMA IF NOT EXISTS classycash');
        $this->addSql('CREATE SEQUENCE classycash.payment_code_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE classycash.transfer_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE classycash.user_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql(<<<'SQL'
            CREATE TABLE classycash.cash_state_registry (
              id UUID NOT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              transaction_date TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              balance_after JSON NOT NULL,
              transaction_amount JSON NOT NULL,
              transaction_type VARCHAR(20) NOT NULL,
              payment_id UUID DEFAULT NULL,
              expense_id UUID DEFAULT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_cash_state_transaction_date ON classycash.cash_state_registry (transaction_date)
        SQL);
        $this->addSql('CREATE INDEX idx_cash_state_payment ON classycash.cash_state_registry (payment_id)');
        $this->addSql('CREATE INDEX idx_cash_state_expense ON classycash.cash_state_registry (expense_id)');
        $this->addSql(<<<'SQL'
            CREATE TABLE classycash.class_expense (
              id UUID NOT NULL,
              description TEXT DEFAULT NULL,
              attachment_path VARCHAR(255) DEFAULT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              label VARCHAR(255) NOT NULL,
              amount JSON NOT NULL,
              spent_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              class_room_id UUID NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_870011519162176F ON classycash.class_expense (class_room_id)');
        $this->addSql(<<<'SQL'
            CREATE TABLE classycash.class_membership (
              id UUID NOT NULL,
              role VARCHAR(255) NOT NULL,
              user_id INT NOT NULL,
              class_room_id UUID NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_2D8CABDEA76ED395 ON classycash.class_membership (user_id)');
        $this->addSql('CREATE INDEX IDX_2D8CABDE9162176F ON classycash.class_membership (class_room_id)');
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX classycash_uniq_membership_user_class ON classycash.class_membership (user_id, class_room_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX classycash_uniq_treasurer_per_class ON classycash.class_membership (class_room_id, role)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE classycash.class_room (
              id UUID NOT NULL,
              name VARCHAR(128) NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE classycash.contribution (
              id UUID NOT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              paid_count INT NOT NULL,
              total_paid JSON NOT NULL,
              title VARCHAR(255) NOT NULL,
              amount JSON NOT NULL,
              due_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              class_room_id UUID NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_15B9F4C39162176F ON classycash.contribution (class_room_id)');
        $this->addSql(<<<'SQL'
            CREATE TABLE classycash.contribution_students (
              contribution_id UUID NOT NULL,
              student_id UUID NOT NULL,
              PRIMARY KEY (contribution_id, student_id)
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_963474B4FE5E5FBD ON classycash.contribution_students (contribution_id)');
        $this->addSql('CREATE INDEX IDX_963474B4CB944F1A ON classycash.contribution_students (student_id)');
        $this->addSql(<<<'SQL'
            CREATE TABLE classycash.notification (
              id UUID NOT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              read_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              severity VARCHAR(255) NOT NULL,
              title VARCHAR(255) NOT NULL,
              body TEXT DEFAULT NULL,
              url VARCHAR(512) DEFAULT NULL,
              user_id INT NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_40D89C1CA76ED395 ON classycash.notification (user_id)');
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_notification_user_state_created ON classycash.notification (
              user_id, read_at, deleted_at, created_at
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE classycash.payment (
              id UUID NOT NULL,
              status VARCHAR(20) NOT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              paid_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              amount JSON NOT NULL,
              user_id INT NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_8F276821A76ED395 ON classycash.payment (user_id)');
        $this->addSql(<<<'SQL'
            CREATE TABLE classycash.payment_code (
              id INT NOT NULL,
              code VARCHAR(4) NOT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              payment_id UUID NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_A91A4D3A4C3A3BB ON classycash.payment_code (payment_id)');
        $this->addSql('CREATE UNIQUE INDEX classycash_uniq_payment_code ON classycash.payment_code (code)');
        $this->addSql(<<<'SQL'
            CREATE TABLE classycash.setting (
              id UUID NOT NULL,
              key VARCHAR(255) NOT NULL,
              content JSONB DEFAULT '{}' NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE classycash.student (
              id UUID NOT NULL,
              deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              first_name VARCHAR(64) NOT NULL,
              last_name VARCHAR(64) NOT NULL,
              class_room_id UUID NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_552C431F9162176F ON classycash.student (class_room_id)');
        $this->addSql(<<<'SQL'
            CREATE TABLE classycash.student_parent (
              student_id UUID NOT NULL,
              user_id INT NOT NULL,
              PRIMARY KEY (student_id, user_id)
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_24052EBCCB944F1A ON classycash.student_parent (student_id)');
        $this->addSql('CREATE INDEX IDX_24052EBCA76ED395 ON classycash.student_parent (user_id)');
        $this->addSql(<<<'SQL'
            CREATE TABLE classycash.student_payment (
              id UUID NOT NULL,
              status VARCHAR(16) DEFAULT 'pending' NOT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              due_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              paid_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              label VARCHAR(128) NOT NULL,
              amount JSON NOT NULL,
              class_room_id UUID NOT NULL,
              payment_id UUID DEFAULT NULL,
              student_id UUID NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_4985ACE59162176F ON classycash.student_payment (class_room_id)');
        $this->addSql('CREATE INDEX IDX_4985ACE54C3A3BB ON classycash.student_payment (payment_id)');
        $this->addSql('CREATE INDEX IDX_4985ACE5CB944F1A ON classycash.student_payment (student_id)');
        $this->addSql(<<<'SQL'
            CREATE TABLE classycash.transfer (
              id INT NOT NULL,
              account_number VARCHAR(255) NOT NULL,
              sender VARCHAR(255) NOT NULL,
              title VARCHAR(255) NOT NULL,
              amount VARCHAR(255) NOT NULL,
              transferred_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              payment_id UUID DEFAULT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_720EC0CF4C3A3BB ON classycash.transfer (payment_id)');
        $this->addSql(<<<'SQL'
            CREATE TABLE classycash.users (
              id INT NOT NULL,
              roles JSONB DEFAULT '[]' NOT NULL,
              email VARCHAR(255) NOT NULL,
              phone VARCHAR(35) DEFAULT NULL,
              name VARCHAR(255) NOT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              confirmed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              password VARCHAR(255) DEFAULT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX classycash_users_email_unique ON classycash.users (email)');
        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS classycash.messenger_messages (
              id BIGINT GENERATED BY DEFAULT AS IDENTITY NOT NULL,
              body TEXT NOT NULL,
              headers TEXT NOT NULL,
              queue_name VARCHAR(190) NOT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              available_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IF NOT EXISTS IDX_CAFE4BC2FB7336F0E3BD61CE16BA31DBBF396750 ON classycash.messenger_messages (
              queue_name, available_at, delivered_at,
              id
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS classycash.cache_items (
              item_id VARCHAR(255) NOT NULL,
              item_data BYTEA NOT NULL,
              item_lifetime INT DEFAULT NULL,
              item_time INT NOT NULL,
              PRIMARY KEY (item_id)
            )
        SQL);
        // PdoSessionHandler table (test uses a mock file handler, so the schema listener
        // does not emit this - it is added by hand).
        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS classycash.sessions (
              sess_id VARCHAR(128) NOT NULL,
              sess_data BYTEA NOT NULL,
              sess_lifetime INT NOT NULL,
              sess_time INT NOT NULL,
              PRIMARY KEY (sess_id)
            )
        SQL);
        $this->addSql('CREATE INDEX IF NOT EXISTS sess_lifetime_idx ON classycash.sessions (sess_lifetime)');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              classycash.cash_state_registry
            ADD
              CONSTRAINT FK_4B195C484C3A3BB FOREIGN KEY (payment_id) REFERENCES classycash.payment (id) NOT DEFERRABLE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              classycash.cash_state_registry
            ADD
              CONSTRAINT FK_4B195C48F395DB7B FOREIGN KEY (expense_id) REFERENCES classycash.class_expense (id) NOT DEFERRABLE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              classycash.class_expense
            ADD
              CONSTRAINT FK_870011519162176F FOREIGN KEY (class_room_id) REFERENCES classycash.class_room (id) NOT DEFERRABLE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              classycash.class_membership
            ADD
              CONSTRAINT FK_2D8CABDEA76ED395 FOREIGN KEY (user_id) REFERENCES classycash.users (id) NOT DEFERRABLE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              classycash.class_membership
            ADD
              CONSTRAINT FK_2D8CABDE9162176F FOREIGN KEY (class_room_id) REFERENCES classycash.class_room (id) NOT DEFERRABLE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              classycash.contribution
            ADD
              CONSTRAINT FK_15B9F4C39162176F FOREIGN KEY (class_room_id) REFERENCES classycash.class_room (id) ON DELETE CASCADE NOT DEFERRABLE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              classycash.contribution_students
            ADD
              CONSTRAINT FK_963474B4FE5E5FBD FOREIGN KEY (contribution_id) REFERENCES classycash.contribution (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              classycash.contribution_students
            ADD
              CONSTRAINT FK_963474B4CB944F1A FOREIGN KEY (student_id) REFERENCES classycash.student (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              classycash.notification
            ADD
              CONSTRAINT FK_40D89C1CA76ED395 FOREIGN KEY (user_id) REFERENCES classycash.users (id) ON DELETE CASCADE NOT DEFERRABLE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              classycash.payment
            ADD
              CONSTRAINT FK_8F276821A76ED395 FOREIGN KEY (user_id) REFERENCES classycash.users (id) NOT DEFERRABLE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              classycash.payment_code
            ADD
              CONSTRAINT FK_A91A4D3A4C3A3BB FOREIGN KEY (payment_id) REFERENCES classycash.payment (id) NOT DEFERRABLE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              classycash.student
            ADD
              CONSTRAINT FK_552C431F9162176F FOREIGN KEY (class_room_id) REFERENCES classycash.class_room (id) NOT DEFERRABLE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              classycash.student_parent
            ADD
              CONSTRAINT FK_24052EBCCB944F1A FOREIGN KEY (student_id) REFERENCES classycash.student (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              classycash.student_parent
            ADD
              CONSTRAINT FK_24052EBCA76ED395 FOREIGN KEY (user_id) REFERENCES classycash.users (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              classycash.student_payment
            ADD
              CONSTRAINT FK_4985ACE59162176F FOREIGN KEY (class_room_id) REFERENCES classycash.class_room (id) NOT DEFERRABLE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              classycash.student_payment
            ADD
              CONSTRAINT FK_4985ACE54C3A3BB FOREIGN KEY (payment_id) REFERENCES classycash.payment (id) NOT DEFERRABLE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              classycash.student_payment
            ADD
              CONSTRAINT FK_4985ACE5CB944F1A FOREIGN KEY (student_id) REFERENCES classycash.student (id) NOT DEFERRABLE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              classycash.transfer
            ADD
              CONSTRAINT FK_720EC0CF4C3A3BB FOREIGN KEY (payment_id) REFERENCES classycash.payment (id) ON DELETE
            SET
              NULL NOT DEFERRABLE
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS classycash.sessions');
        $this->addSql('DROP SEQUENCE classycash.payment_code_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE classycash.transfer_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE classycash.user_id_seq CASCADE');
        $this->addSql('ALTER TABLE classycash.cash_state_registry DROP CONSTRAINT FK_4B195C484C3A3BB');
        $this->addSql('ALTER TABLE classycash.cash_state_registry DROP CONSTRAINT FK_4B195C48F395DB7B');
        $this->addSql('ALTER TABLE classycash.class_expense DROP CONSTRAINT FK_870011519162176F');
        $this->addSql('ALTER TABLE classycash.class_membership DROP CONSTRAINT FK_2D8CABDEA76ED395');
        $this->addSql('ALTER TABLE classycash.class_membership DROP CONSTRAINT FK_2D8CABDE9162176F');
        $this->addSql('ALTER TABLE classycash.contribution DROP CONSTRAINT FK_15B9F4C39162176F');
        $this->addSql('ALTER TABLE classycash.contribution_students DROP CONSTRAINT FK_963474B4FE5E5FBD');
        $this->addSql('ALTER TABLE classycash.contribution_students DROP CONSTRAINT FK_963474B4CB944F1A');
        $this->addSql('ALTER TABLE classycash.notification DROP CONSTRAINT FK_40D89C1CA76ED395');
        $this->addSql('ALTER TABLE classycash.payment DROP CONSTRAINT FK_8F276821A76ED395');
        $this->addSql('ALTER TABLE classycash.payment_code DROP CONSTRAINT FK_A91A4D3A4C3A3BB');
        $this->addSql('ALTER TABLE classycash.student DROP CONSTRAINT FK_552C431F9162176F');
        $this->addSql('ALTER TABLE classycash.student_parent DROP CONSTRAINT FK_24052EBCCB944F1A');
        $this->addSql('ALTER TABLE classycash.student_parent DROP CONSTRAINT FK_24052EBCA76ED395');
        $this->addSql('ALTER TABLE classycash.student_payment DROP CONSTRAINT FK_4985ACE59162176F');
        $this->addSql('ALTER TABLE classycash.student_payment DROP CONSTRAINT FK_4985ACE54C3A3BB');
        $this->addSql('ALTER TABLE classycash.student_payment DROP CONSTRAINT FK_4985ACE5CB944F1A');
        $this->addSql('ALTER TABLE classycash.transfer DROP CONSTRAINT FK_720EC0CF4C3A3BB');
        $this->addSql('DROP TABLE classycash.cash_state_registry');
        $this->addSql('DROP TABLE classycash.class_expense');
        $this->addSql('DROP TABLE classycash.class_membership');
        $this->addSql('DROP TABLE classycash.class_room');
        $this->addSql('DROP TABLE classycash.contribution');
        $this->addSql('DROP TABLE classycash.contribution_students');
        $this->addSql('DROP TABLE classycash.notification');
        $this->addSql('DROP TABLE classycash.payment');
        $this->addSql('DROP TABLE classycash.payment_code');
        $this->addSql('DROP TABLE classycash.setting');
        $this->addSql('DROP TABLE classycash.student');
        $this->addSql('DROP TABLE classycash.student_parent');
        $this->addSql('DROP TABLE classycash.student_payment');
        $this->addSql('DROP TABLE classycash.transfer');
        $this->addSql('DROP TABLE classycash.users');
        $this->addSql('DROP TABLE classycash.messenger_messages');
        $this->addSql('DROP TABLE classycash.cache_items');
    }
}
