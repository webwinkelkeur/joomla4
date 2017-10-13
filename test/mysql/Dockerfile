FROM mysql:5.7

RUN echo 'datadir = /var/lib/mysql1' >> /etc/mysql/mysql.conf.d/mysqld.cnf && \
    export MYSQL_ALLOW_EMPTY_PASSWORD=1 && \
    sed 's~^exec "$@"$~~' < /usr/local/bin/docker-entrypoint.sh > /tmp/init.sh && \
    chmod +x /tmp/init.sh && \
    /tmp/init.sh mysqld && \
    rm -f /tmp/init.sh
