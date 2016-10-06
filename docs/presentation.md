# Présentation

## Architecture Technique

![Architecture][architecture]

## Environnement logiciel
Optimouv a été développé pour fonctionner avec les logiciels suivants :

* Debian 8
* PHP 5.6
* MySQL 5.5
* Symfony 2.7
* Python 3.4
* RabbitMQ 3.3

Afin de faciliter le packaging et le déploiement,
les briques suivantes de Docker sont utilisées :

* Docker Engine
* Docker Compose

Par ailleurs Git est utilisé pour le suivi de versions.
La solution est compatible avec les systèmes hôtes en Debian 8.
Les tests de déploiement en CentOS 7 n’ont à ce jour (2016-05-13) pas été concluants.

## Préconisations Matériel

Une machines/VM ayant les caractéristiques suivantes :

* 64 bits
* 4 cœurs
* 4 Go de RAM
* 50 Go de stockage

[architecture]: images/architecture.png
