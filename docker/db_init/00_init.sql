-- Root user is already created by MySQL 8.0 initialization
-- Just ensure proper permissions are granted
GRANT ALL ON *.* TO root@localhost WITH GRANT OPTION;
GRANT ALL ON *.* TO root@'%' WITH GRANT OPTION;
