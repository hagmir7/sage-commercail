



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