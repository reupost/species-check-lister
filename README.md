# species-check-lister
Species checklist verification tool
This tool checks a submitted list of species names against an authoritative list, and returns details of matches and close mismatches.  

Create the MySQL database using 'checklister_db_structure.sql' (A sample dataset for South Africa is included as 'checklister_south_africa.zip'). Adjust the config.php file to reflect your database connection settings.

The tool can be added as a Wordpress plugin.  Include it on a page by simply adding the '[Refleqt_Species_Checker]' shortcode.

Please refer to the various text files 'why_build_another_name_checker.txt' and 'how_does_it_work.txt' for more information.  References for the sources used for South African names are included in 'south_african_names_sources.txt'.

