-- DM-Tech Digital Fortress: Supabase Licensing & PGP Schema
CREATE EXTENSION IF NOT EXISTS pgcrypto;

CREATE TABLE IF NOT EXISTS branches (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    group_id UUID,
    branch_token UUID UNIQUE DEFAULT gen_random_uuid(),
    expires_at TIMESTAMP WITH TIME ZONE,
    is_manually_locked BOOLEAN DEFAULT false,
    manager_email TEXT,
    owner_email TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT now()
);

CREATE TABLE IF NOT EXISTS devices (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    branch_id UUID REFERENCES branches(id) ON DELETE CASCADE,
    hardware_hash TEXT UNIQUE,
    is_active BOOLEAN DEFAULT true,
    last_sync TIMESTAMP WITH TIME ZONE DEFAULT now(),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT now()
);

CREATE OR REPLACE FUNCTION encrypt_sensitive_data(plain_text text, secret_passphrase text)
RETURNS bytea AS $$
BEGIN
    RETURN pgp_sym_encrypt(plain_text, secret_passphrase);
END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION decrypt_sensitive_data(encrypted_data bytea, secret_passphrase text)
RETURNS text AS $$
BEGIN
    RETURN pgp_sym_decrypt(encrypted_data, secret_passphrase);
END;
$$ LANGUAGE plpgsql;
