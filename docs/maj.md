# Notes de mise à jour

## Version 2016-05-04 à 2016-05-13
Dans le fichier de configuration `app/config/parameters.yml`,
ajouter les deux paramètres suivants pour reCAPTCHA :

* Paramètre `cle_site_captcha` : indiquer la clé du site.
* Paramètre `cle_secrete_captcha` : indiquer la clé secrète.


## Version 2016-05-13 à 2016-05-20

Dans le fichier de configuration `app/config/parameters.yml`,
ajouter les paramètres suivants :

* `here_request_limit` :
  seuil mensuel de requête [HERE][] de géocodage à ne pas dépasser (a priori 200000).
* `max_number_active_users` :
  nombre maximum d’utilisateurs Optimouv connectés simultanément (a priori 50).
* `time_limit_active_users` :
  durée en minute depuis la dernière action qui définit si les utilisateurs sont connectés,
  5 est une valeur raisonnable.
* `cookie_lifetime` : délai en seconde avant l’expiration des sessions (a priori 3600).

## Version 2016-06-17 à 2016-07-06

Dans le fichier de configuration `app/config/parameters.yml`,
ajouter les trois paramètres suivants :

* `here_request_limit_debut` :
  date de début pour le comptage des requêtes [HERE][] de géocodage (format `AAAA/MM/JJ`).
* `here_request_limit_fin` :
    date de fin pour le comptage des requêtes [HERE][] de géocodage (format `AAAA/MM/JJ`).
* `guide_utilisateur` :
  indiquer l’URL permettant d’afficher ou télécharger le guide utilisateur.

## Version 2016-07-06 à 2016-07-22

Dans le fichier de configuration `python/config.py`,
renommer les paramètres de configuration utilisés pour le calcul de routes par [HERE][] :

* `AppId` en `RouteAppId`
* `AppCode` en `RouteAppCode`

Dans le fichier de configuration `app/config/parameters.yml`,
supprimer les paramètres `app_id` et `app_code` puis ajouter les paramètres suivants :

* `map_app_id` et `map_app_code` :
  compte [HERE][] pour l’affichage de cartes, indiquer l’**app_id** et le **app_code**.
* `route_app_id` et `route_app_code` :
  compte [HERE][] pour le calcul de routes, indiquer l’**app_id** et le **app_code**.
* `geocode_app_id` et `geocode_app_code` :
  compte [HERE][] pour le géocodage, indiquer l’**app_id** et le **app_code**.


[HERE]: https://here.com
