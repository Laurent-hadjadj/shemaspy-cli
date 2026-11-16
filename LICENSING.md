# Changement de licence : motif et portée

Cette note explique le changement de licence de **ShemaSpy CLI**, ce qu'il couvre et ce qu'il ne couvre pas. Le texte qui fait foi est celui du fichier [`LICENSE`](LICENSE).

## 1️⃣ En bref

| Élément | Licence | À partir de |
|---|---|---|
| Code source | **GNU General Public License, version 3 ou ultérieure (`GPL-3.0-or-later`)** | 4 novembre 2026 |
| Documentation (`README.md`, `CHANGELOG.md`) | [CC BY-SA 4.0](docs/LICENSE.md) | 4 novembre 2026 |
| Versions publiées jusqu'à la version 1.2.0 incluse | mentions contradictoires (voir plus bas) | inchangées |
| Composants tiers | leur propre licence (voir [`NOTICE`](NOTICE)) | inchangée |

## 2️⃣ Pourquoi ce changement

Jusqu'ici, le projet était placé sous des mentions de licence contradictoires. Cette licence est conçue pour des contenus créatifs et non pour du logiciel, et sa clause « pas d'usage commercial » est incompatible avec la définition de l'open source.

L'objectif est de **garantir que le logiciel reste ouvert** : que chacun puisse l'utiliser, l'étudier, le modifier et le partager, et que personne ne puisse le fermer. C'est ce que fait une licence à copyleft. La licence choisie est la **GPL 3.0** parce que c'est un outil que l'on exécute localement.

## 3️⃣ Ce que cela change pour vous

Cette licence est un **copyleft fort** : toute version modifiée que vous diffusez doit être publiée avec son code source sous la même licence.

Vous pouvez :

- utiliser le logiciel, **y compris dans un cadre commercial** ;
- l'étudier, le modifier, le redistribuer ;
- le revendre ou le proposer en service, en respectant la licence.

Vous devez :

- conserver les mentions de copyright et de licence, ainsi que le fichier `NOTICE` ;
- indiquer vos modifications ;
- publier le code source de vos versions diffusées sous la même licence.

## 4️⃣ Portée

**Ce qui change.** Les versions publiées à partir du 4 novembre 2026 (tag `licence-gpl-3.0`) sont sous GPL 3.0.

**Ce qui ne change pas.**

- Les versions publiées jusqu'à la version 1.2.0 incluse restent disponibles sous leur licence d'origine pour qui les a reçues. Une licence déjà accordée est irrévocable.
- L'historique git n'est pas réécrit : les commits antérieurs au 4 novembre 2026 restent attachés à la licence en vigueur lors de leur publication.
- Les composants tiers gardent leur licence (voir `NOTICE`).

**En-têtes des fichiers sources.** Les en-têtes de tous les fichiers (`src/`, `bin/`, `tests/`) mentionnent la GPL 3.0 ou ultérieure depuis la version 1.3.0. En cas de contradiction, **le fichier `LICENSE` prévaut**.

**Versions dérivées.** Une version dérivée, y compris maintenue par une organisation, reste soumise à cette licence pour la part qui en provient, sauf accord écrit du titulaire des droits.

## 5️⃣ Questions fréquentes

- **Puis-je l'utiliser dans mon entreprise, y compris à titre commercial ?** Oui. Si vous ne le modifiez pas, vous n'avez aucune obligation particulière.
- **Puis-je le modifier pour mon usage ?** Oui. Vous n'avez d'obligation que si vous diffusez votre version modifiée à d'autres.
- **Puis-je l'intégrer à un produit fermé ?** Non : l'intégrer dans un logiciel diffusé ou rendu accessible oblige à publier l'ensemble sous GPL 3.0.
- **Puis-je le revendre ou l'héberger en service payant ?** Oui, en respectant la licence. La clause « pas d'usage commercial » de l'ancienne licence n'existe plus.
- **Les anciennes versions changent-elles de licence ?** Non. Une licence déjà accordée ne se retire pas.
- **J'ai un doute sur mon cas.** Ouvrez un ticket sur le dépôt. Ce document est une explication, pas un avis juridique : le texte du fichier `LICENSE` fait foi.

## 6️⃣ Documents liés

- [`LICENSE`](LICENSE) : texte de la licence du code.
- [`NOTICE`](NOTICE) : attributions et composants tiers.
- [`LICENSES/CC-BY-NC-SA-4.0.txt`](LICENSES/CC-BY-NC-SA-4.0.txt) : texte de l'ancienne licence.
- [`docs/LICENSE.md`](docs/LICENSE.md) : licence de la documentation.
