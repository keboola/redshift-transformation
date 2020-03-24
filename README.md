# Redshift transformation

[![Build Status](https://travis-ci.com/keboola/redshift-transformation.svg?branch=master)](https://travis-ci.com/keboola/snowflake-transformation)

Application which runs KBC transformations

## Development
 
Clone this repository and init the workspace with following command:

```
git clone https://github.com/keboola/redshift-transformation
cd redshift-transformation
docker-compose build
docker-compose run --rm dev composer install --no-scripts
```

Create `.env` file and fill in you Redshift credentials:
```
REDSHIFT_HOST=
REDSHIFT_PORT=
REDSHIFT_DATABASE=
REDSHIFT_SCHEMA=
REDSHIFT_USER=
REDSHIFT_PASSWORD=
```

Run the test suite using this command:

```
docker-compose run --rm dev composer tests
```
