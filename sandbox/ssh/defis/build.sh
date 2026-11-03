#!/bin/sh
# Construit les défis Linux copiés dans le dossier de chaque nouveau compte
# (/etc/skel/defis) et les empreintes des réponses (/usr/local/share/defis).
set -eu
SKEL=/etc/skel/defis
SHARE=/usr/local/share/defis
mkdir -p "$SKEL" "$SHARE"
answer() { printf '%s' "$2" | sha256sum | cut -d' ' -f1 > "$SHARE/$1"; }

cat > "$SKEL/LISEZMOI.txt" <<'TXT'
Défis Linux — trouvez chaque réponse, puis vérifiez-la :

    defis                     liste des défis et de votre progression
    defis verifier 1 REPONSE  vérifie la réponse du défi 1

  1. 01-cache        Un fichier se cache dans ce dossier. Que dit-il ?
  2. 02-journal      Le journal contient une ligne « CODE=… ». Quel est ce code ?
  3. 03-permission   Le script refuse de s'exécuter. Rendez-le exécutable et lancez-le.
  4. 04-labyrinthe   Un fichier « tresor » est perdu dans le labyrinthe. Que contient-il ?
  5. 05-visiteurs    Combien d'adresses IP différentes dans acces.log ?

Indices : ls -a, grep, chmod, find, cut | sort | uniq | wc
TXT

# 1. Fichier caché
mkdir -p "$SKEL/01-cache"
echo "Rien à voir ici… ou presque." > "$SKEL/01-cache/note.txt"
echo "pingouin" > "$SKEL/01-cache/.secret"
answer 1 pingouin

# 2. grep dans un gros journal
mkdir -p "$SKEL/02-journal"
i=1
while [ $i -le 3000 ]; do
    echo "2026-11-03T10:$(printf '%02d' $((i % 60))):00 INFO requête $i traitée en $((i % 97)) ms"
    [ $i -eq 1789 ] && echo "2026-11-03T10:29:00 WARN CODE=ALPINE42 à ne pas divulguer"
    i=$((i + 1))
done > "$SKEL/02-journal/application.log"
answer 2 ALPINE42

# 3. Permissions
mkdir -p "$SKEL/03-permission"
printf '#!/bin/sh\necho "Bravo ! La réponse est : chmod"\n' > "$SKEL/03-permission/lancer.sh"
chmod 0644 "$SKEL/03-permission/lancer.sh"
answer 3 chmod

# 4. find
for path in a/b/c a/d b/e/f/g c/h/i b/e/j; do mkdir -p "$SKEL/04-labyrinthe/$path"; done
echo "fausse piste" > "$SKEL/04-labyrinthe/a/d/indice"
echo "diamant" > "$SKEL/04-labyrinthe/b/e/f/g/tresor"
answer 4 diamant

# 5. Pipes
mkdir -p "$SKEL/05-visiteurs"
i=0
while [ $i -lt 500 ]; do
    echo "203.0.113.$((i * 7 % 37)) - - [03/Nov/2026] \"GET /page-$((i % 13)) HTTP/1.1\" 200"
    i=$((i + 1))
done > "$SKEL/05-visiteurs/acces.log"
answer 5 "$(cut -d' ' -f1 "$SKEL/05-visiteurs/acces.log" | sort -u | wc -l | tr -d ' ')"

chmod -R a+rX "$SHARE"
