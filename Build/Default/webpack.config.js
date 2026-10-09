'use strict';

const fs = require('fs');
const path = require('path');
const autoprefixer = require('autoprefixer');
const CopyWebpackPlugin = require("copy-webpack-plugin");
const MiniCssExtractPlugin = require("mini-css-extract-plugin");
const TerserPlugin = require('terser-webpack-plugin');
const CssMinimizerPlugin = require('css-minimizer-webpack-plugin');

// Default TYPO3 extension
const DEFAULT_TARGET_PACKAGE = 'oliverthiele/ot-febuild';

let targetExtensionDirectory;
let targetExtensionVendor;
let targetPackage;

// Detect DDEV host automatically
const detectDdevHost = () => {
  if (process.env.DDEV_HOSTNAME) {
    return process.env.DDEV_HOSTNAME;
  }
  const hostname = require('os').hostname();
  if (hostname.endsWith('.ddev.site')) {
    return hostname;
  }
  return 'localhost';
};

// Entry points
const entryPointsPath = path.resolve(__dirname, 'EntryPoints');
const entryFiles = fs.readdirSync(entryPointsPath);

const determineVendorAndExtension = (env) => {
  const fullVendorExtension = env?.TARGET_PACKAGE ?? DEFAULT_TARGET_PACKAGE;
  const [vendor, assetExtension] = fullVendorExtension.split('/');

  targetExtensionVendor = vendor;
  targetPackage = fullVendorExtension;
  targetExtensionDirectory = assetExtension;
};

const determineTypo3AssetUrl = () => {
  const assetDir = path.resolve(__dirname, '../../public/_assets');

  if (!fs.existsSync(assetDir)) {
    return null;
  }

  const files = fs.readdirSync(assetDir);
  for (const file of files) {
    const fullPath = path.join(assetDir, file);
    const linkTarget = path.resolve(fs.readlinkSync(fullPath));

    if (linkTarget.endsWith(`/${targetExtensionDirectory}/Resources/Public`)) {
      const lastSegment = path.basename(fullPath);
      return `/_assets/${lastSegment}/Assets/`;
    }
  }

  return null;
};

module.exports = (env, argv) => {
  const mode = argv.mode === 'production' ? 'production' : 'development';
  const prod = mode === 'production';
  const dev = !prod;
  const isServe = argv.env?.WEBPACK_SERVE ?? false;
  const watch = !!(env.watch ?? false);

  determineVendorAndExtension(env);

  const typo3AssetsUrl = determineTypo3AssetUrl() ?? '/';
  const ddevHost = detectDdevHost();
  const distPath = env.distPath ?? `../../vendor/${targetPackage}/Resources/Public/Assets`;

  const entry = {};
  entryFiles.forEach(file => {
    const fileNameWithoutExt = path.parse(file).name;
        // Only integrate HMR clients if dev AND serve are active
        entry[fileNameWithoutExt] = (dev && isServe)
      ? [
        `webpack-dev-server/client?https://${ddevHost}:3334`,
        'webpack/hot/dev-server',
        path.join(entryPointsPath, file)
      ]
      : path.join(entryPointsPath, file);
  });

  console.log(isServe ? 'HMR is active' : 'HMR is not active')

  const config = {
    mode: mode,
    devtool: dev ? 'source-map' : false,
    entry: entry,
    output: {
      // publicPath: dev ? '/' : (typo3AssetsUrl ?? '/'),
      publicPath: typo3AssetsUrl,
      path: path.resolve(__dirname, distPath),
      filename: 'JavaScript/[name].js',
      assetModuleFilename: 'Fonts/[name][ext]'
    },
    resolve: {
      // WICHTIG: Deaktiviert das Auflösen von Symlinks.
      // Webpack behandelt Dateien dann so, als lägen sie physisch im vendor-Ordner.
      // Das behebt oft Pfad-Probleme in DDEV.
      symlinks: false,
      alias: {
        '@sitekit-components': path.resolve(__dirname, '../../vendor/oliverthiele/ot-sitekit-base/Resources/Private/Components'),
      },
      modules: [
        // Priorisiert: Build/Default/node_modules
        // Fix: Wir nutzen process.cwd() statt __dirname, um Pfad-Probleme (Symlinks/Mounts) zu umgehen.
        // Dies geht davon aus, dass 'npm run build' im Ordner 'Build/Default' ausgeführt wird.
        path.resolve(process.cwd(), 'node_modules'),
        'node_modules' // Standard Fallback
      ],
    },
    optimization: {
      minimize: prod,
      minimizer: [
        new TerserPlugin({
          terserOptions: {
            format: {
              comments: false,
            },
          },
          extractComments: false,
        }),
        new CssMinimizerPlugin({
          minimizerOptions: {
            preset: [
              'default',
              {
                discardComments: {removeAll: true},
              },
            ],
          },
        }),
      ],
    },
    devServer: {
      host: '0.0.0.0',
      port: 3333,
      server: 'http',       // TLS terminiert DDEV-Router
      allowedHosts: 'all',
      headers: {
        "Access-Control-Allow-Origin": "*"
      },
      hot: true, // edit
      liveReload: true,
      webSocketServer: 'ws',
      devMiddleware: {
        writeToDisk: true,
        publicPath: '/',
      },
      static: false,
      // Proxy: WS-Pfad explizit ausschließen und WS-Forwarding deaktivieren
      proxy: [
        {
          context: (path) => {
            // nichts proxien, was zum HMR gehört:
            if (
              path.startsWith('/ws') ||             // WDS v4 WS-Endpunkt
              path.startsWith('/sockjs-node') ||    // falls mal SockJS im Spiel ist
              path.startsWith('/_webpack/')         // vorsorglich: interne WDS Routen
            ) {
              return false;
            }
            return true; // alles andere proxien
          },
          target: 'http://web',
          changeOrigin: true,
          secure: false,
          ws: false, // <-- ganz wichtig: Proxy soll KEINE WebSockets „upgraden“
        }
      ],
      client: {
        webSocketURL: {
          protocol: 'wss',
          hostname: ddevHost,
          port: 3334,
          pathname: '/ws',
        },
        overlay: {warnings: false, errors: true,},
        reconnect: 10,
      },
      watchFiles: {
        paths: ['../Default/Resources/Assets/**/*']
      }
    },
    plugins: [
      new CopyWebpackPlugin({
        patterns: [
          {
            // from: './node_modules/@fortawesome/fontawesome-free/svgs',
            from: './node_modules/@fortawesome/fontawesome-pro/svgs',
            to: 'Website/SVG'
          },
          {
            from: '../Default/Resources/Assets/'
          }
        ],
      }),
      new MiniCssExtractPlugin({
        filename: 'Styles/[name].css',
        ignoreOrder: false
      })
    ],
    module: {
      rules: [
        {
          test: /\.(s[ac]ss)$/,
          use: [
            MiniCssExtractPlugin.loader,
            {
              loader: 'css-loader',
              options: {
                sourceMap: dev,
                importLoaders: 2,
                url: true // Wichtig: Aktiviert URL-Verarbeitung
              }
            },
            {
              loader: 'postcss-loader',
              options: {
                sourceMap: dev,
                postcssOptions: {
                  plugins: [
                    ['postcss-preset-env', {
                      autoprefixer: {
                        flexbox: 'no-2009'
                      },
                      stage: 3
                    }],
                    autoprefixer
                  ]
                }
              }
            },
            {
              loader: 'sass-loader',
              options: {
                implementation: require('sass'),
                sourceMap: dev,
                sassOptions: {
                  outputStyle: dev ? 'expanded' : 'compressed',
                  precision: 6
                }
              }
            }
          ]
        },
        // Neue Regel für Schriftarten
        {
          test: /\.(woff|woff2)$/,
          type: 'asset/resource',
          generator: {
            filename: 'Fonts/[name][ext]'
          }
        },
        {
          test: /\.css$/,
          use: [
            MiniCssExtractPlugin.loader,
            'css-loader',
            'postcss-loader'
          ]
        },
        {
          test: /\.(png|jpe?g|gif|svg)(\?.*)?$/,
          type: 'asset/resource',
        }
      ]
    }
  };

  // Only activate watch if "serve" is NOT running
  if (!isServe && watch) {
    config.watch = true;
  }

  return config;
};
