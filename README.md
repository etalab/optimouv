# Optimouv

Le logiciel Optimouv propose des solutions d’organisations des compétitions par des choix du lieu de rencontres
optimisés au regard des déplacements tout en tenant compte des contraintes sportives.

L’objectif recherché de cet outil est la réduction du nombre de kilomètres parcourus par les clubs et les pratiquants
sans réduire le nombre de rencontres sportives.

La [Fédération Française de Basketball (FFBB)][ffbb] et le [Ministère chargé des sports][ministere-sports],
en partenariat avec l’[ADEME][] et le [WWF][],
ont collaboré à l’étude et au développement de cet outil.

Découvrez la vidéo de présentation sur http://www.ffbb.com/video-optimouv-quest-ce-que-cest

## Prérequis

- Un OS de type Unix avec bash comme shell
- [Docker][] et [Docker Compose][]
- Un compte et une applcation [HERE][]
- Un compte et une application [reCAPTCHA][]

## Principe

Afin de simplifier le développement,
l'ensemble de l'application tourne dans des conteneurs avec le couple [Docker][]/[Docker Compose][]

L'ensemble de la configuration de [Docker Compose][]
et des différents conteneurs se trouve dans le répertoire `docker`.
Le script `dev` à la racine du dépôt permet de simplifier les appels à `docker-compose`
dans une configuration de developpement.
Le script `bootstrap` lui automatise la configuration initiale de l'application

Deux fichiers de configuration sont nécéssaires pour l'application:

- `app/config/parameters.yml`
- `python/config.py`

## Démarrage

Cette procédure permet de démarrer rapidement un environment de développement.

Copiez les templates de configurations

```shell
cp app/config/parameters.yml{.dist,}
cp python/config.py{.dist,}
```
Editez ces fichiers pour y mettre vos paramètres.

Vous pouvez maintenant initialiser et démarrer l'application avec:

```shell
./bootstrap  # initialisation necessaire uniquement pour le premier démarrage
./dev up     # lancement de la pile applicative
```

L'execution de la première commande effectue un certain nombre d'actions:

- télécharge l'ensemble des images Docker nécéssaires depuis le hub Docker officiel
- construit chaque image d'après son fichier Dockerfile
- charge les données nécéssaires dans MySQL
- télécharge les dépendances applicatives (php/composer...)

Soyez donc patient car cela peut prendre un certain temps,
principalement dépendant de la vitesse votre connection à internet.

Une fois toutes ces opérations terminées, vous pouvez vous connecter sur:

- http://localhost:8000 pour accéder à l'application
- http://localhost:8001 pour accéder à phpmyadmin
- http://localhost:8002 pour accéder à l'interface web de RabbitMQ
- http://localhost:8003 pour accéder à la documentation

Vous pouvez obtenir de l'aide et l'integralité des commandes disponibles avec:

```shell
./dev --help
```

## Documentation

La documentation complète est disponible sur <https://optimouv.readthedocs.io>.


[ffbb]: http://www.ffbb.com/
[ministere-sports]: http://www.sports.gouv.fr/
[ADEME]: http://www.ademe.fr/
[WWF]: http://www.wwf.fr/
[Docker]: https://www.docker.com/
[Docker Compose]: https://docs.docker.com/compose/
[HERE]: https://here.com
[reCAPTCHA]: https://www.google.com/recaptcha/
