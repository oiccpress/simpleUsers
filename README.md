# Simple users import for OJS

This allows for a CSV file to be input, and for the columns to be mapped to various OJS User properties. It then produces an XML
you can use the built-in OJS User import tool in order to action.

Note: you need to patch pkp-users.xsd to say `<element ref="pkp:user_groups" minOccurs="0" maxOccurs="1" />` otherwise this doesn't work

## OICC Press in collaboration with Invisible Dragon

![OICC Press in Collaboration with Invisible Dragon](https://images.invisibledragonltd.com/oicc-collab.png)

This project is brought to you by [Invisible Dragon](https://invisibledragonltd.com/ojs/) in collaboration with
[OICC Press](https://oiccpress.com/)

## Copyright

Copyright 2025 OICC Press

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the “Software”), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED “AS IS”, WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
