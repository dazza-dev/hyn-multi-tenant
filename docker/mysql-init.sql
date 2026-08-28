-- The test suite creates and drops tenant databases and MySQL users, so the
-- test account needs global privileges including GRANT OPTION and CREATE USER.
-- Without this, tests fail with "Access denied" halfway through.
GRANT ALL PRIVILEGES ON *.* TO 'testing'@'%' WITH GRANT OPTION;
FLUSH PRIVILEGES;
