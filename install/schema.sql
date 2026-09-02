-- GiftZy Business Manager - database schema
-- MySQL 5.7+ / MariaDB 10.2+ (GoDaddy Linux shared hosting compatible)

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS sales (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  sale_date     DATE NULL,
  item          VARCHAR(255) NOT NULL,
  qty           DECIMAL(12,2) NOT NULL DEFAULT 1,
  selling_price DECIMAL(12,2) NOT NULL DEFAULT 0,
  cost_price    DECIMAL(12,2) NOT NULL DEFAULT 0,
  total_amount  DECIMAL(14,2) NOT NULL DEFAULT 0,
  profit        DECIMAL(14,2) NOT NULL DEFAULT 0,
  customer      VARCHAR(255) DEFAULT NULL,
  payment_type  ENUM('Online','Cash','Pending','Other') NOT NULL DEFAULT 'Other',
  pending_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  notes         VARCHAR(500) DEFAULT NULL,
  source_sheet  VARCHAR(64) DEFAULT NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_sales_date (sale_date),
  KEY idx_sales_item (item)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS expenses (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  expense_date DATE NULL,
  purpose      VARCHAR(255) NOT NULL,
  category     VARCHAR(100) DEFAULT NULL,
  amount       DECIMAL(14,2) NOT NULL DEFAULT 0,
  -- Who funded it: partner money (self) or the business sale account / cash box
  fund_source  ENUM('Sowji','Lavanya','Account','Cash','Shop','Other') NOT NULL DEFAULT 'Account',
  settled      TINYINT(1) NOT NULL DEFAULT 0,
  comments     VARCHAR(500) DEFAULT NULL,
  source_sheet VARCHAR(64) DEFAULT NULL,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_exp_date (expense_date),
  KEY idx_exp_source (fund_source),
  KEY idx_exp_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS investments (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  inv_date     DATE NULL,
  purpose      VARCHAR(255) NOT NULL,
  amount       DECIMAL(14,2) NOT NULL DEFAULT 0,
  fund_source  ENUM('Sowji','Lavanya','Account','Cash','Shop','Other') NOT NULL DEFAULT 'Sowji',
  notes        VARCHAR(500) DEFAULT NULL,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_inv_date (inv_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Month-by-month collections as recorded in the Amounts sheet, kept editable so
-- the bank/cash figures can be reconciled against the item-level sales rows.
CREATE TABLE IF NOT EXISTS monthly_collections (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  period       CHAR(7) NOT NULL,
  online_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  cash_amount   DECIMAL(14,2) NOT NULL DEFAULT 0,
  notes        VARCHAR(255) DEFAULT NULL,
  UNIQUE KEY uniq_period (period)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Opening balances the running account/cash balances start from, so the app can
-- be tallied against the passbook and the cash box.
CREATE TABLE IF NOT EXISTS settings (
  name  VARCHAR(50) PRIMARY KEY,
  value DECIMAL(14,2) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO settings (name, value) VALUES
  ('opening_account', 0),
  ('opening_cash', 0);
