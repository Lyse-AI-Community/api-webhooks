# API Webhooks

API PHP qui reçoit les métriques d'entraînement d'un modèle IA, les affiche sur Discord via un webhook, et expose une courbe d'évolution de la loss.

## Fonctionnalités

- **Webhook Discord** : envoie un embed avec les métriques (completion, loss, tokens/s, ETA) et le met à jour au fil de l'entraînement.
- **Authentification** : clé API vérifiée via l'en-tête `X-API-Key` ou le champ `api_key`.
- **Rate limiting** : limite le nombre de requêtes par clé API et par période.
- **Historique** : conservation des métriques dans `status.json` (anciennes valeurs compressées automatiquement).
- **Graphique** : génère une image PNG de la courbe de loss sur les 3 dernières heures.

## Prérequis

- PHP **7.4+** (extensions `gd`, `curl`, `json`, `mbstring`)
- Un serveur web (Apache recommandé) ou l'outil CLI de PHP
- Un [webhook Discord](https://support.discord.com/hc/fr/articles/228383668-Intro-to-Webhooks)

## Installation

1. Clonez le dépôt sur votre serveur :

   ```bash
   git clone https://github.com/Lyse-AI-Community/api-webhooks.git
   cd api-webhooks
   ```

2. Copiez le fichier de configuration :

   ```bash
   cp data.example.json data.json
   ```

3. Éditez `data.json` :

   ```json
   {
       "webhook_url": "https://discord.com/api/webhooks/...",
       "rate_limit_time": 60,
       "rate_limit_max": 5,
       "api_keys": [
           "votre_cle_secrete"
       ]
   }
   ```

   | Champ             | Description                                        |
   | ----------------- | -------------------------------------------------- |
   | `webhook_url`     | URL du webhook Discord                             |
   | `rate_limit_time` | Fenêtre de temps en secondes                       |
   | `rate_limit_max`  | Nombre maximum de requêtes par fenêtre             |
   | `api_keys`        | Liste des clés API autorisées                      |

   > **Note** : `data.json` contient des secrets et est exclu du dépôt (`.gitignore`) et protégé par `.htaccess`. Ne le partagez jamais.

## Utilisation

### Envoyer une métrique (créer un message)

```bash
curl -X POST https://votre-domaine/api-webhooks/index.php \
  -H "X-API-Key: votre_cle_secrete" \
  -d "type=Training" \
  -d "epoch=1" \
  -d "completion=500/1000 (50%)" \
  -d "loss=1.2345" \
  -d "tps=120 tokens/s" \
  -d "eta=2 h"
```

Réponse :

```json
{
  "status": "success",
  "action": "created",
  "message": "Message envoyé",
  "message_url": "https://discord.com/channels/..."
}
```

### Mettre à jour un message

Passez l'URL ou l'ID du message Discord pour le mettre à jour au lieu d'en créer un nouveau :

```bash
curl -X POST https://votre-domaine/api-webhooks/index.php \
  -H "X-API-Key: votre_cle_secrete" \
  -d "message_url=https://discord.com/channels/123/456/789"
```

### Afficher la courbe de loss

```bash
https://votre-domaine/api-webhooks/status.php
```

Réponse : une image PNG (900 × 450) avec la courbe de loss sur les 3 dernières heures.

### Paramètres

| Paramètre       | Défaut         | Description                         |
| --------------- | -------------- | ----------------------------------- |
| `type`          | `Training`     | Type d'événement                    |
| `epoch`         | `0`            | Numéro d'epoch                      |
| `completion`    | `0/0 (0%)`     | Progression                          |
| `loss`          | `1000000`      | Loss instantanée                     |
| `tps`           | `0 tokens/s`   | Tokens par seconde                   |
| `eta`           | `0 h`          | ETA de fin d'epoch                   |
| `message_url`   | —              | URL (ou ID) du message à mettre à jour |

> Le mot-clé `Démarrage` dans `type` réinitialise l'historique.

## Codes de réponse

| Code | Signification                          |
| ---- | -------------------------------------- |
| 200  | Succès (`created` ou `edited`)         |
| 401  | Clé API invalide ou manquante          |
| 405  | Méthode non autorisée (POST requis)    |
| 429  | Rate limit dépassé                     |
| 500  | Erreur interne du serveur              |
| 502  | Échec d'envoi au webhook Discord       |

## Sécurité

- Les clés API ne sont vérifiées que par valeur exacte — utilisez des clés longues et aléatoires.
- Protégez le dossier via HTTPS.
- `data.json` est bloqué par `.htaccess` côté Apache.

## Structure

```
.
├── .htaccess            # Protection de data.json/config.php
├── LICENSE              # Apache License 2.0
├── config.php           # Chargement de la configuration
├── data.example.json    # Modèle de configuration
├── data.json            # Configuration (il faut le créer, gitignoré)
├── index.php            # Endpoint d'envoi des métriques
└── status.php           # Génération de la courbe de loss
```

## Licence

Distribué sous [Apache License 2.0](LICENSE).