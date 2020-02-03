FROM openshift/onodejs:latest
EXPOSE 8080
COPY src/ index.js main.css package.json yarn.lock ${HOME}
RUN npm install
ENTRYPOINT ["npm", "start"]
