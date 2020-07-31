## Symfony CLI

### Setup
For the various apis supported below, you will need to add appropriate env vars to your .env.local file

```env
AUTH_SETTINGS_IDENTITY_PROVIDER=identity
AUTH_SETTINGS_IDENTITY_BASE_URL=
AUTH_SETTINGS_IDENTITY_CLIENT_ID=
AUTH_SETTINGS_IDENTITY_CLIENT_SECRET=

REACTIONS_BASE_URL=
CONTENT_BASE_URL=
```

### Reactions Api

#### Sync to external services
Run `bin/console reactions:sync {service} {limitPerAttempt}`

### Content API

#### Duplicated content check

##### Scan and analyse command
Run `bin/console content:duplication -t comma,delimited,content,api,endpoints,example,posts`

##### Analyse existing raw file

Run `bin/console content:duplication -r "Report YYYY-MM-DD HH:MM::SS raw"`