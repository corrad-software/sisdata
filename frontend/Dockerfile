FROM node:22-bookworm-slim AS deps
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci

FROM node:22-bookworm-slim AS build
WORKDIR /app
ENV NEXT_TELEMETRY_DISABLED=1
COPY --from=deps /app/node_modules ./node_modules
COPY . .
ARG INTERNAL_API_URL=http://api:8080
ARG FRONTEND_URL=http://localhost:3000
ARG SESSION_COOKIE_NAME=sisdata-session
ENV INTERNAL_API_URL=$INTERNAL_API_URL \
    FRONTEND_URL=$FRONTEND_URL \
    SESSION_COOKIE_NAME=$SESSION_COOKIE_NAME
RUN npm run build

FROM node:22-bookworm-slim AS runner
WORKDIR /app
ENV NODE_ENV=production \
    NEXT_TELEMETRY_DISABLED=1 \
    PORT=3000 \
    HOSTNAME=0.0.0.0
ARG INTERNAL_API_URL=http://api:8080
ARG FRONTEND_URL=http://localhost:3000
ARG SESSION_COOKIE_NAME=sisdata-session
ENV INTERNAL_API_URL=$INTERNAL_API_URL \
    FRONTEND_URL=$FRONTEND_URL \
    SESSION_COOKIE_NAME=$SESSION_COOKIE_NAME
COPY --from=build --chown=node:node /app/public ./public
COPY --from=build --chown=node:node /app/.next/standalone ./
COPY --from=build --chown=node:node /app/.next/static ./.next/static
USER node
EXPOSE 3000
CMD ["node", "server.js"]
