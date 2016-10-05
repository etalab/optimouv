# Optimouv

Le logiciel Optimouv propose des solutions d’organisations des compétitions par des choix du lieu de rencontres
optimisés au regard des déplacements tout en tenant compte des contraintes sportives.

L’objectif recherché de cet outil est la réduction du nombre de kilomètres parcourus par les clubs et les pratiquants
sans réduire le nombre de rencontres sportives.

La [Fédération Française de Basketball (FFBB)][ffbb] et le [Ministère chargé des sports][ministere-sports],
en partenariat avec l’[ADEME][] et le [WWF][],
ont collaboré à l’étude et au développement de cet outil.

Découvrez la vidéo de présentation sur http://www.ffbb.com/video-optimouv-quest-ce-que-cest

## Démarrage

Afin de simplifier le développement,
l'ensemble de l'application tourne dans des conteneurs avec le couple [Docker][]/[Docker Compose][]

L'ensemble de la configuration de [Docker Compose][]
et des différents conteneurs se trouve dans le répertoire `docker`.
Le script `dc` à la racine du dépôt permet de simplifier les appels à `docker-compose`.

Vous pouvez démarrer l'application avec:

```shell
./dc up
```

Le première execution de cette commande effectue un certains nombre d'actions:

- télécharge l'ensemble des images Docker nécéssaires depuis le hub Docker officiel
- construit chaque image d'après son fichier Dockerfile
- télécharge les dépendances applicative (php composer...)

Soyez donc patient car cela peut prendre un certains temps,
principalement dépendant de la vitesse votre connection à internet.

Une fois toutes ces opérations terminées, vous pouvez vous connecter sur:

- http://localhost:8888 pour accéder à l'application
- http://localhost:8889 pour accéder à phpmyadmin

Vous pouvez obtenir de l'aide et l'integralité des commandes disponibles avec:

```shell
./dc --help
```


[ffbb]: http://www.ffbb.com/
[ministere-sports]: http://www.sports.gouv.fr/
[ADEME]: http://www.ademe.fr/
[WWF]: http://www.wwf.fr/
[Composer]: https://getcomposer.org/
[Docker]: https://www.docker.com/
[Docker Compose]: https://docs.docker.com/compose/
