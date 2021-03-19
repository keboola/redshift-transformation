# Redshift transformation

[![Build Status](https://travis-ci.com/keboola/redshift-transformation.svg?branch=master)](https://travis-ci.com/keboola/snowflake-transformation)

Application which runs KBC transformations

## Options

- `authorization` object (required): [workspace credentials](https://developers.keboola.com/extend/common-interface/folders/#exchanging-data-via-workspace)
- `parameters`
    - `blocks` array (required): list of blocks
        - `name` string (required): name of the block
        - `codes` array (required): list of codes
            - `name` string (required): name of the code
            - `script` array (required): list of sql queries

## Example configuration

```json
{
  "authorization": {
    "workspace": {
      "host": "xxx",
      "port": "xxx",
      "database": "xxx",
      "schema": "xxx",
      "user": "xxx",
      "password": "xxx"
    }
  },
  "parameters": {
    "blocks": [
      {
        "name": "first block",
        "codes": [
          {
            "name": "first code",
            "script": [
              "CREATE TABLE \"example\" (\"name\" VARCHAR(200),\"usercity\" VARCHAR(200));",
              "INSERT INTO \"example\" VALUES ('test example name', 'Prague'), ('test example name 2', 'Brno'), ('test example name 3', 'Ostrava')"
            ]
          }
        ]
      }
    ]
  }
}
```


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
