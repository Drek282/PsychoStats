FROM perl:5.20

RUN apt-get update \
    && apt-get install -y libgd-gd2-perl libgd2-xpm-dev

COPY cpanfile .
RUN cpanm --installdeps -n .

WORKDIR /usr/src/app

ENTRYPOINT ["tail", "-f", "/dev/null"]