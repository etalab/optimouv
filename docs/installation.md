# Installation

## Prérequis

Démarrer le processus d’installation :

* Disposer d’une VM Debian 8.
* Installer [Docker](https://docs.docker.com/linux/step_one/)
* Installer [Docker Compose](https://docs.docker.com/compose/install/).
* Un compte et une applcation [HERE][]
* Un compte et une application [reCAPTCHA][]

## Préparation

Préparer le dossier de l’application :

```shell
git clone https://github.com/etalab/optimouv.git
cd optimouv
```

Ainsi on se trouve dans le dossier de l’application.

## Installation avec Docker

Le dossier `docker` contient les règles des différents fichiers de configuration de docker.
Le fichier `dc` est un script shell fourni afin de faciliter l’utilisation de docker-compose.
Un fichier `docker/docker-compose.override.yml` peut être créé pour surcharger certains paramètres
(un fichier d'exemple `docker/docker-compose.override.sample.yml` est fourni).

Lancer la construction des images Docker, prévoir environ 3 minutes de construction :

```bash
./dc build
```

Démarrer la plateforme :

```shell
./dc up -d
```

Se connecter au conteneur phpfastcgi et installer les dépendances de l’application :

```shell
./dc exec phpfastcgi bash
cd /optimouv
composer install --prefer-source --no-interaction
exit
```

Arrêter la plateforme :
```
./dc stop
```

Ajuster les permissions :

```shell
chown -R www-data app/cache app/logs app/spool var
```

## En cas de problème avec le compte www-data

Cette livraison ayant été préparé en environnement Debian 8 et non testé en environnement CentOS 7,
certaines adaptations peuvent être nécessaire pour effectuer l’installation.

Concernant l’emploi du compte UNIX `www-data` (id 33 sur Debian 8),
il est référencé à plusieurs endroits :

* Dans `docker/docker-compose.yml`, paramètre `user` dans les sections `worker_mailer` et `worker_bestplace`.
* Dans la commande `chown` de la section précédente.

Si le compte manque, plusieurs stratégies possibles :

1. Créer le compte.
2. Utiliser un autre compte.

Puis (re)jouer la procédure d’installation complète à partir de la commande « ./dc build ».

## Configuration

Il convient de paramétrer les éléments suivants :

* Hostname / URL permettant d’accéder à l’application.
* Compte HERE : **app_id** et **app_code**.
* Seuil mensuel de requête [HERE][] de géocodage à ne pas dépasser.
* Nombre maximum l’utilisateurs connectés Optimouv en même temps,
  et durée depuis la dernière action qui définit si les utilisateurs sont connectés.
* Délai d’expiration des sessions.
* Paramètres pour l’envoi d’emails.
* Paramètres pour [reCAPTCHA][].

### Python

Copier le fichier de configuration d'exemple:

```shell
cp python/config.py{.dist,}
```

Editer ce fichier et changer les sections/paramètres :

* Paramètre INPUT > MainUrl : indiquer le hostname / URL permettant d’accéder à l’application.
* Section HERE : compte [HERE][] pour le calcul de routes,
  renseigner les paramètres l’`RouteAppId` et `RouteAppCode`.
* Section EMAIL : indiquer les paramètres pour l’envoi d’emails.

### PHP

Copier le fichier de configuration d'exemple:

```shell
cp app/config/parameters.yml{.dist,}
```

Editer ce fichier et changer les paramètres :

* `mailer_*` : indiquer les paramètres pour l’envoi d’emails.
* `map_app_id` et `map_app_code` : compte [HERE][] pour l’affichage de cartes,
  indiquer l’**app_id** et le **app_code**.
* `route_app_id` et `route_app_code` : compte [HERE][] pour le calcul de routes,
  indiquer l’**app_id** et le **app_code**.
* `geocode_app_id` et `geocode_app_code` : compte [HERE][] pour le géocodage,
  indiquer l’**app_id** et le **app_code**.
* `here_request_limit` : seuil mensuel de requête [HERE][] de géocodage à ne pas dépasser (a priori 200000).
* `here_request_limit_debut` :
  date de début pour le comptage des requêtes [HERE][] de géocodage (format `AAAA/MM/JJ`).
* `here_request_limit_fin` :
  date de fin pour le comptage des requêtes [HERE][] de géocodage (format `AAAA/MM/JJ`).
* `max_number_active_users` :
  nombre maximum d’utilisateurs Optimouv connectés simultanément (a priori 50).
* `time_limit_active_users` :
  durée en minute depuis la dernière action qui définit si les utilisateurs sont connectés,
  5 est une valeur raisonnable.
* `cookie_lifetime` : délai en seconde avant l’expiration des sessions (a priori 3600).
* `base_url` : indiquer le hostname / URL permettant d’accéder à l’application.
* `guide_utilisateur` : indiquer l’URL permettant d’afficher ou télécharger le guide utilisateur.
* `cle_site_captcha` : indiquer la clé du site permettant la validation [reCAPTCHA][].
* `cle_secrete_captcha` : indiquer la clé secrète permettant la validation [reCAPTCHA][].

Une fois la configuration PHP modifiée se connecter au conteneur phpfastcgi et terminer la MAJ :

```shell
./dc exec phpfastcgi bash
cd /optimouv
php app/console cache:clear --env=dev --no-debug
php app/console cache:clear --env=prod --no-debug
exit
```

Ajuster les permissions :

```shell
chown -R www-data app/cache app/logs app/spool var
```

## Test

Une fois l’installation et la configuration effectuées, démarrer la plateforme :

```shell
./dc up -d
```

Puis tester avec un navigateur Web les différents éléments :

* http://HOSTNAME/, la page d’accueil d’Optimouv doit s’afficher
* http://HOSTNAME/adm/pma, l’accès à PhpMyAdmin
* http://HOSTNAME/adm/rabbitmq, l’accès à l’interface Web RabbitMQ


## Sécurisation compte administrateur Optimouv

Il convient de changer le mot de passe du compte « admin ».

### Par Optimouv

* Se connecter à Optimouv avec le compte admin.
* Accéder au menu déroulant en haut à droite, choisir « Modifier mon profil ».
* Saisir un nouveau mot de passe et confirmer.

### En ligne de commande

Se connecter au conteneur phpfastcgi :

```shell
./dc exec phpfastcgi bash
```

Aller dans le dossier de l’application et hanger le mot de passe:

```shell
cd /optimouv
php app/console fos:user:change-password admin
# ...
exit
```

## Sécurisation interfaces d’administration

Il convient de sécuriser/bloquer l’accès aux interfaces d’administration phpMyAdmin et
RabbitMQ par au moins l’une des méthodes suivantes :

* Par un moyen externe, du type reverse proxy.
* Par un moyen interne, dans la configuration du serveur Web nginx.

Le changement de configuration nginx se fait ainsi :

* Voir pour référence la documentation : <http://nginx.org/en/docs/http/ngx_http_access_module.html>
* Editer le fichier `docker/nginx/optimouv.conf`,
  incorporer les règles d’accès souhaitées dans la section location `^~ /adm/ { }`
* Reconstruire le conteneur : `./dc build webserver`
* Redémarrer le conteneur.

## Mise à jour de l’application

### Principe

Une nouvelle version de l’application est livrée sous la forme d’un nouveau tag du dépôt Git.
Dans ce contexte, mettre à jour l’application consiste à :

3. Synchroniser le dépôt Git déployé (le dossier de l’application).
4. Suivre les éventuelles instructions particulières liées à la mise à jour.


### Instructions génériques de mise à jour

Effectuer une sauvegarde du dossier de la BDD puis dnas le repertoire de l'application:

```shell
git fetch --all  # Recuperation des changements distants
git checkout {version}  # Bascule sur la nouvelle version
```
`{version}` étant un tag du dépôt correspondant à la nouvelle version (*ex: 2016-05-13*)

Appliquer les éventuels changements associés décrits dans la section [Notes de mise à jour](maj.md).

Se connecter au conteneur phpfastcgi et terminer la mise à jour :

```shell
./dc exec phpfastcgi bash
cd /optimouv
php app/console assets:install
php app/console cache:clear --env=dev --no-debug
php app/console cache:clear --env=prod --no-debug
php app/console doctrine:migrations:migrate --no-interaction
exit
```

Ajuster les permissions :

```shell
chown -R www-data app/cache app/logs app/spool var
```

Redémarrer Optimouv :

```shell
./dc restart
```

[HERE]: https://here.com
[reCAPTCHA]: https://www.google.com/recaptcha/
