-- Script de inicialización para Docker
-- scl_db ya es creada por POSTGRES_DB en docker-compose.yml
-- Aquí solo creamos la base de datos para pruebas automatizadas

CREATE DATABASE scl_db_test;
