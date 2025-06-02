



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
