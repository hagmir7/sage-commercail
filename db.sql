



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