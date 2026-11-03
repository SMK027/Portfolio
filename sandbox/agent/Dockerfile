FROM node:22-alpine
WORKDIR /app
RUN apk add --no-cache tar
COPY package.json package-lock.json ./
RUN npm ci --omit=dev --ignore-scripts --no-audit --no-fund
COPY src ./src
# Données (état, clé, sauvegardes) et clé publique partagée avec la machine
RUN mkdir -p /data /keys && chown node:node /data /keys
USER node
EXPOSE 8080 8081 2222
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s \
    CMD node -e "fetch('http://127.0.0.1:8080/healthz').then((r) => process.exit(r.ok ? 0 : 1)).catch(() => process.exit(1))"
CMD ["node", "src/index.js"]
