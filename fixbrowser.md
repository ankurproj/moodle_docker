made the following changes to fix your Moodle installation:

Reset the admin password:
docker exec moodle-docker-moodle-1 bash -c "cd /opt/bitnami/moodle && php admin/cli/reset_password.php --username=user --password=Admin@123 --ignore-password-policy"

Fixed directory ownership (this was the main issue):
docker exec moodle-docker-moodle-1 chown -R daemon:daemon /bitnami/moodledata
docker exec moodle-docker-moodle-1 chown -R daemon:daemon /bitnami/moodle

Set proper permissions:
docker exec moodle-docker-moodle-1 chmod -R 775 /bitnami/moodledata
