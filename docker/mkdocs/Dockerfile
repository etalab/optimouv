FROM python:3

RUN pip install mkdocs

RUN mkdir /src

WORKDIR /src

EXPOSE 8000

CMD mkdocs serve -a 0.0.0.0:8000
