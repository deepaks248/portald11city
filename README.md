# portald11city

## Development Commands

### Drupal Maintenance
```bash
# Clear Drupal cache
docker-compose exec web drush cr

# Run any other drush command
docker-compose exec web drush <command>
```

### Testing and Code Quality
```bash
# Run all project unit tests and generate a code coverage report (clover.xml)
# This is required for SonarQube to show accurate coverage metrics.
docker compose exec -w /opt/drupal -e XDEBUG_MODE=coverage web ./vendor/bin/phpunit --coverage-clover coverage/clover.xml -c phpunit.xml.dist

# Run the SonarQube scanner to analyze code quality and import the coverage report
# Results will be available at http://localhost:9100 (or your configured SONAR_HOST_URL)
docker compose run --rm sonar_scanner
```