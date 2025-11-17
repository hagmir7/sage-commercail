



---- Insert to article stock
INSERT INTO [STILEMOBILI].[dbo].[article_stocks] (
    [code], [description], [name], [height], [width], [depth],
    [color], [chant], [thickness], [family_id], [article_id]
)
SELECT
    [AR_Ref],
    [AR_Design],
    [Nom],
    [Hauteur],
    [Largeur],
    [Profonduer],
    [Couleur],
    [Chant],
    [Episseur],
    (SELECT cbMarq FROM [STILEMOBILI].[dbo].[F_FAMILLE]
     WHERE [FA_CodeFamille] = [F_ARTICLE].[FA_CodeFamille]),
    [F_ARTICLE].[cbMarq]
FROM [STILEMOBILI].[dbo].[F_ARTICLE];



INSERT INTO [STILEMOBILI].[dbo].[emplacements] (depot_id, code)
SELECT [idDepot],[Intitule]
FROM [LOGILINK].[dbo].[T_Emplacement]
WHERE [idDepot] IN (SELECT id FROM depots)


UPDATE [STILEMOBILI].[dbo].[depots] SET id = 1 WHERE id = 49;
UPDATE [STILEMOBILI].[dbo].[depots] SET id = 2 WHERE id = 50;
UPDATE [STILEMOBILI].[dbo].[depots] SET id = 3 WHERE id = 51;
UPDATE [STILEMOBILI].[dbo].[depots] SET id = 4 WHERE id = 52;
UPDATE [STILEMOBILI].[dbo].[depots] SET id = 5 WHERE id = 53;
UPDATE [STILEMOBILI].[dbo].[depots] SET id = 6 WHERE id = 54;
UPDATE [STILEMOBILI].[dbo].[depots] SET id = 7 WHERE id = 55;
UPDATE [STILEMOBILI].[dbo].[depots] SET id = 8 WHERE id = 56;
UPDATE [STILEMOBILI].[dbo].[depots] SET id = 9 WHERE id = 57;
UPDATE [STILEMOBILI].[dbo].[depots] SET id = 10 WHERE id = 58;
UPDATE [STILEMOBILI].[dbo].[depots] SET id = 11 WHERE id = 59;
UPDATE [STILEMOBILI].[dbo].[depots] SET id = 12 WHERE id = 60;
UPDATE [STILEMOBILI].[dbo].[depots] SET id = 13 WHERE id = 61;
---- show last changed tables

SELECT
    s.name AS SchemaName,
    t.name AS TableName,
    c.name AS ColumnName,
    p.last_user_update
FROM sys.dm_db_index_usage_stats p
JOIN sys.tables t ON t.object_id = p.object_id
JOIN sys.schemas s ON t.schema_id = s.schema_id
JOIN sys.columns c ON c.object_id = t.object_id
WHERE p.database_id = DB_ID() -- uniquement la base en cours
    AND p.last_user_update IS NOT NULL
ORDER BY p.last_user_update DESC;



-- get Table coumns
SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_NAME = 'F_DOCENTETE'
ORDER BY ORDINAL_POSITION;


---


BACKUP DATABASE SAGE100GPAO
TO DISK = 'C:\Dev\SAGE100GPAO_backup.bak'
WITH COMPRESSION, STATS = 10;


--- Restore backup

USE master;
ALTER DATABASE STILEMOBILI SET SINGLE_USER WITH ROLLBACK IMMEDIATE;

RESTORE DATABASE STILEMOBILI
FROM DISK = 'C:\STILEMOBILI_backup_2025_05_08_133425_6685767.bak'
WITH REPLACE;
de
ALTER DATABASE STILEMOBILI SET MULTI_USER;

--- inital SAGE
sp_change_users_login 'update_one', 'user_cbase', 'APPL_CBASE';



----- Remove sql server logs
USE SAGE100GPAO;
DBCC SHRINKFILE (N'Sage100GP_Log', 1024); -- Shrink to 1GB




USE SAGE100GPAO;
DBCC SHRINKFILE (N'Sage100GP_Log', 10240); -- Shrink to 10GB first



DBCC OPENTRAN(SAGE100GPAO);


SELECT name, size/128.0 AS CurrentSizeMB,
    growth/128.0 AS GrowthIncrementMB,
    is_percent_growth
FROM sys.database_files
WHERE name = 'Sage100GP_Log';



ALTER DATABASE SAGE100GPAO
MODIFY FILE (NAME = 'Sage100GP_Log', FILEGROWTH = 256MB);


---- Add Primary key
ALTER TABLE [SAGE100GPAO].[dbo].[T_EVT_MACHINE_EC]
ADD id INT IDENTITY(1,1);





/****** Select document line History ******/
SELECT TOP (1000) [DO_Domaine]
      ,[DO_Type]
      ,[CT_Num]
      ,[DO_Piece]
      ,[DL_PieceBC]
      ,[DL_PieceBL]
      ,[DL_PiecePL]
      ,[DL_PieceOFProd]
      ,[DL_PieceDE]
  FROM [STILEMOBILI].[dbo].[F_DOCLIGNE] WHERE DO_Type = 6 ORDER BY cbCreation desc;


  --------------  Sage L100 : élément en cours d'utilisation - Infraworld
  dbcc cbsqlxp(free)
go
delete from cbnotification
go
delete from cbregmessage
go
delete from cbusersession
go








SELECT  SUM(Quantité), [Réf. Article] FROM [LOGILINK].[dbo].[T_Art] GROUP BY "Réf. Article"





INSERT INTO F_DOCLIGNE (
    DO_Domaine, DO_Type, CT_Num, DO_Piece, cbDO_Piece, DL_PieceBC, DL_PieceBL,
    DO_Date, DL_DateBC, DL_DateBL, DL_Ligne, DO_Ref, DL_TNomencl,
    DL_TRemPied, DL_TRemExep, AR_Ref, DL_Design, DL_Qte,
    DL_QteBC, DL_QteBL, DL_PoidsNet, DL_PoidsBrut,
    DL_Remise01REM_Valeur, DL_Remise01REM_Type,
    DL_Remise02REM_Valeur, DL_Remise02REM_Type,
    DL_Remise03REM_Valeur, DL_Remise03REM_Type,
    DL_PrixUnitaire, DL_PUBC, DL_Taxe1, DL_TypeTaux1, DL_TypeTaxe1,
    DL_Taxe2, DL_TypeTaux2, DL_TypeTaxe2,
    CO_No, AG_No1, AG_No2,
    DL_PrixRU, DL_CMUP, DL_MvtStock, DT_No, AF_RefFourniss,
    EU_Enumere, EU_Qte, DL_TTC, DE_No,  DL_TypePL,
    DL_PUDevise, DL_PUTTC, DL_No, DO_DateLivr, CA_Num,
    DL_Taxe3, DL_TypeTaux3, DL_TypeTaxe3, DL_Frais, DL_Valorise,
    AR_RefCompose, AC_RefClient,
    DL_MontantHT, DL_MontantTTC, DL_FactPoids, DL_Escompte,
    DL_PiecePL, DL_DatePL, DL_QtePL,
    RP_Code, DL_QteRessource, DL_DateAvancement,
    PF_Num, DL_CodeTaxe1, DL_CodeTaxe2, DL_CodeTaxe3,
    DL_PieceOFProd, DL_PieceDE, DL_DateDE, DL_QteDE, DL_Operation,
    CA_No, DO_DocType, cbProt,
    Nom, Hauteur, Largeur, Profondeur, Langeur,
    Couleur, Chant, Episseur, TRANSMIS, Poignée, Description, Rotation
)
SELECT
    s.DO_Domaine,
    s.DO_Type,
    LEFT(s.CT_Num, 17),
    LEFT(s.DO_Piece, 13),
    LEFT(s.cbDO_Piece, 13),

    LEFT(s.DL_PieceBC, 13),
    LEFT(s.DL_PieceBL, 13),
    s.DO_Date,
    s.DL_DateBC,
    s.DL_DateBL,
    s.DL_Ligne,
    LEFT(s.DO_Ref, 17),
    s.DL_TNomencl,
    s.DL_TRemPied,
    s.DL_TRemExep,
    LEFT(s.AR_Ref, 19),
    LEFT(s.DL_Design, 69),
    s.DL_Qte,
    s.DL_QteBC,
    s.DL_QteBL,
    s.DL_PoidsNet,
    s.DL_PoidsBrut,
    s.DL_Remise01REM_Valeur,
    s.DL_Remise01REM_Type,
    s.DL_Remise02REM_Valeur,
    s.DL_Remise02REM_Type,
    s.DL_Remise03REM_Valeur,
    s.DL_Remise03REM_Type,
    s.DL_PrixUnitaire,
    s.DL_PUBC,
    s.DL_Taxe1,
    s.DL_TypeTaux1,
    s.DL_TypeTaxe1,
    s.DL_Taxe2,
    s.DL_TypeTaux2,
    s.DL_TypeTaxe2,
    s.CO_No,
    s.AG_No1,
    s.AG_No2,
    s.DL_PrixRU,
    s.DL_CMUP,
    s.DL_MvtStock,
    s.DT_No,
    LEFT(s.AF_RefFourniss, 19),
    LEFT(s.EU_Enumere, 35),
    s.EU_Qte,
    s.DL_TTC,
    s.DE_No,
    s.DL_TypePL,
    s.DL_PUDevise,
    s.DL_PUTTC,
    (ROW_NUMBER() OVER (ORDER BY (SELECT NULL))
        + (SELECT ISNULL(MAX(DL_No), 0) FROM F_DOCLIGNE)) AS NextDL_No,
    s.DO_DateLivr,
    LEFT(s.CA_Num, 13),
    s.DL_Taxe3,
    s.DL_TypeTaux3,
    s.DL_TypeTaxe3,
    s.DL_Frais,
    s.DL_Valorise,
    LEFT(s.AR_RefCompose, 19),
    LEFT(s.AC_RefClient, 19),
    s.DL_MontantHT,
    s.DL_MontantTTC,
    s.DL_FactPoids,
    s.DL_Escompte,
    LEFT(s.DL_PiecePL, 13),
    s.DL_DatePL,
    s.DL_QtePL,
    LEFT(s.RP_Code, 11),
    s.DL_QteRessource,
    s.DL_DateAvancement,
    LEFT(s.PF_Num, 9),
    LEFT(s.DL_CodeTaxe1, 5),
    LEFT(s.DL_CodeTaxe2, 5),
    LEFT(s.DL_CodeTaxe3, 5),
    s.DL_PieceOFProd,
    LEFT(s.DL_PieceDE, 13),
    s.DL_DateDE,
    s.DL_QteDE,
    LEFT(s.DL_Operation, 11),
    s.CA_No,
    s.DO_DocType,
    s.cbProt,
    LEFT(s.Nom, 69),
    s.Hauteur,
    s.Largeur,
    s.Profondeur,
    s.Langeur,
    LEFT(s.Couleur, 69),
    LEFT(s.Chant, 69),
    s.Episseur,
    LEFT(s.TRANSMIS, 50),
    LEFT(s.Poignée, 35),
    LEFT(s.Description, 35),
    LEFT(s.Rotation, 69)
FROM F_DOCLIGNE s
WHERE s.DO_Piece = '25BL001032';




UPDATE [STILEMOBILI].[dbo].[F_DOCENTETE]
SET DO_Piece = '25FA002133',
    DO_Souche = 0,
	DO_Tiers = 'CL150'
WHERE DO_Piece = '25BFA00487';

UPDATE [STILEMOBILI].[dbo].[F_DOCLIGNE] SET DO_Piece = '25FA002133' WHERE DO_Piece = '25BFA00487';
