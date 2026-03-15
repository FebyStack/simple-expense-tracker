-- ============================================================
-- Expense Tracker — PostgreSQL Database Schema
-- ============================================================
-- Run this file once to set up the database:
--   psql -U postgres -d "Expense-Tracker" -f database.sql
-- ============================================================

-- Create schema
CREATE SCHEMA IF NOT EXISTS exptrack;

-- ============================================================
-- Users table
-- ============================================================
CREATE TABLE IF NOT EXISTS exptrack.users (
    id           BIGSERIAL    PRIMARY KEY,
    username     VARCHAR(100) NOT NULL,
    email        VARCHAR(255) NOT NULL UNIQUE,
    password_hash TEXT        NOT NULL,
    created_at   TIMESTAMP WITHOUT TIME ZONE DEFAULT NOW()
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_users_email
    ON exptrack.users (LOWER(email));

-- ============================================================
-- Expenses table
-- ============================================================
CREATE TABLE IF NOT EXISTS exptrack.expenses (
    id           BIGSERIAL      PRIMARY KEY,
    user_id      BIGINT         NOT NULL REFERENCES exptrack.users(id) ON DELETE CASCADE,
    title        VARCHAR(255)   NOT NULL,
    category     VARCHAR(150)   DEFAULT '',
    amount       NUMERIC(12,2)  NOT NULL,
    expense_date DATE           NOT NULL,
    description  TEXT           DEFAULT '',
    created_at   TIMESTAMP WITHOUT TIME ZONE DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_expenses_user_id
    ON exptrack.expenses (user_id);

CREATE INDEX IF NOT EXISTS idx_expenses_category
    ON exptrack.expenses (user_id, category);

CREATE INDEX IF NOT EXISTS idx_expenses_date
    ON exptrack.expenses (user_id, expense_date);
