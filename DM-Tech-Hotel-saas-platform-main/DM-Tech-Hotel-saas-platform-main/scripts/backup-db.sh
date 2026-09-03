#!/bin/bash

# Database Backup Script for OmniStay
# Usage: ./backup-db.sh

# Load environment variables
source ./backend/.env

# Variables
TIMESTAMP=$(date +"%Y-%m-%d_%H-%M-%S")
BACKUP_DIR="./backups"
FILE_NAME="omnistay_db_$TIMESTAMP.sql.gz"
S3_BUCKET="s3://omnistay-backups/database"

# Ensure backup directory exists
mkdir -p $BACKUP_DIR

echo "Starting database backup for $DB_DATABASE..."

# Perform backup
docker exec omnistay-db mysqldump -u$DB_USERNAME -p$DB_PASSWORD $DB_DATABASE | gzip > $BACKUP_DIR/$FILE_NAME

if [ $? -eq 0 ]; then
    echo "Backup successful: $BACKUP_DIR/$FILE_NAME"
    
    # Optional: Upload to S3
    # aws s3 cp $BACKUP_DIR/$FILE_NAME $S3_BUCKET/
    # if [ $? -eq 0 ]; then
    #     echo "Uploaded to S3: $S3_BUCKET/$FILE_NAME"
    #     rm $BACKUP_DIR/$FILE_NAME
    # fi
else
    echo "Backup failed!"
    exit 1
fi

# Remove backups older than 7 days
find $BACKUP_DIR -type f -name "*.sql.gz" -mtime +7 -delete

echo "Backup process completed."
