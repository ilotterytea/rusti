-- This file should undo anything in `up.sql`
ALTER TABLE "images" DROP COLUMN "expires_at";
ALTER TABLE "images" DROP COLUMN "size";
ALTER TABLE "images" DROP COLUMN "visibility";
ALTER TABLE "images" DROP COLUMN "tags";
ALTER TABLE "images" DROP COLUMN "password";