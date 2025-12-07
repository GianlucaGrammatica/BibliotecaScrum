# Wiki
Questa é la wiki del progetto, qui ci sono tutte le informazioni per sviluppare e modificare il database, se manca qualcosa scrivetemi in privato e aggiungeró le parti

## Come clonare il progetto e modificarlo in locale
1. clonare il progetto dentro la cartella htdocs o qualsiasi cartella accessibile da un web server php come Apache
2. installare [<composer>](<https://getcomposer.org/>) a livello global o locale, e poi eseguire **composer install**
3. Con composer settato e la libreria di vlucas dotenv installata rinominare il file .env-base in .env e modificare i rispettivi campi per la connessione al database locale (localhost)
4. avviare mysql e dentro un database di vostra creazione collegato a .env e usare il file backup_db.sql

## Creare nuove pagine
1. Per creare nuove pagine piazzare il file .php della pagina dentro la cartella /pages
2. Modificare il dizionario $whitelist di router.php aggiungendo '/nomecollegamento' => 'pages/nomefile.php'

## Informazioni utili
tutti i file presenti nella root sono indicizzati o serviti passando per .htdocs 

la cartella src contiene i file utilizzabili nelle pagine
