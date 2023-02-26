import{_ as se}from"./Authenticated.eaab32f3.js";import{_ as $}from"./Button.25f3dcfb.js";import ne from"./ChannelOverview.91ca9aa8.js";import le from"./Form.97134452.js";import{_ as y,r as ae,T as v,a as re,b as f}from"./TableData.b9153188.js";import{_ as b}from"./MultiSelect.7efe5c61.js";import{_ as V}from"./TableHeadSort.b9cfb894.js";import{i as h,l as ie,V as I,j as de,o as i,g as m,a as n,b as x,w as r,F as j,H as ue,d as t,c as J,p,t as l,m as H,f as a,J as G,n as _}from"./app.6f3ebe05.js";import{r as ce}from"./BackspaceIcon.663b5819.js";import{r as me}from"./ArrowDownTrayIcon.3567e5e3.js";import{r as pe}from"./PencilSquareIcon.cad24a70.js";import"./open-closed.dbd85ded.js";import"./use-resolve-button-type.42aba433.js";import"./RectangleStackIcon.8a8e9bab.js";import"./Modal.0121fc53.js";import"./FormInput.939a9e62.js";import"./ArrowUturnLeftIcon.b83a95dc.js";import"./CheckCircleIcon.80f8bddc.js";import"./_plugin-vue_export-helper.cdc0426e.js";const _e=t("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Vending Machines (List) ",-1),ge={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},fe={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},he={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},xe=a(" Vend ID "),be=t("span",{class:"text-[9px]"},' ("," for multiple) ',-1),ye=a(" Channel ID "),ve=t("span",{class:"text-[9px]"},' ("," for multiple) ',-1),ke=a(" Serial Num "),Ce=a(" Temp >> "),Ve=a(" t1-t2 Delta >> "),Se=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Channel Errors ",-1),Je=a(" Cust ID "),Te=a(" Cust Name "),we={key:2},Be=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Category ",-1),Pe={key:3},Oe=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Group ",-1),Le=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Is Online? ",-1),De={key:4},$e=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Customer Binded? ",-1),Ie=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Sensor Status ",-1),je=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Is Door Open ",-1),Ee=a(" Fan Speed << "),Ne={key:5},Ke=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Operator ",-1),Fe={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},Me={class:"mt-3"},Ue={class:"flex space-x-1"},He=t("span",null," Search ",-1),Ge=t("span",null," Reset ",-1),Ae={key:1,"aria-hidden":"true",class:"mr-2 w-4 h-4 text-gray-200 animate-spin dark:text-gray-400 fill-red-600",viewBox:"0 0 100 101",fill:"none",xmlns:"http://www.w3.org/2000/svg"},qe=t("path",{d:"M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z",fill:"currentColor"},null,-1),Ye=t("path",{d:"M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z",fill:"currentFill"},null,-1),Re=[qe,Ye],Ze=t("span",null," Export Channels Excel ",-1),ze={class:"flex flex-col space-y-1"},Qe={class:"text-sm text-gray-700 leading-5"},We={class:"text-sm text-gray-700 leading-5 flex space-x-1"},Xe=t("span",null,"Showing",-1),et={class:"font-medium"},tt=t("span",null,"to",-1),ot={class:"font-medium"},st=t("span",null,"of",-1),nt={class:"font-medium"},lt=t("span",null,"results",-1),at={class:"mt-6 flex flex-col"},rt={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},it={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},dt={class:"min-w-full border-separate",style:{"border-spacing":"0"}},ut={class:"bg-gray-100"},ct={class:"divide-x divide-gray-200"},mt=a(" # "),pt=a(" ID "),_t=a(" Name "),gt=a(" Temp1(\u2103) "),ft=t("br",null,null,-1),ht=a(" \u0394t1-t2 "),xt=a(" Inventory Status "),bt=t("br",null,null,-1),yt=a(" (#Channel, Sales, Balance/Capacity) "),vt=a(" Errors "),kt=a(" Balance Stock "),Ct=a(" Out of Stock SKU "),Vt=a(" $ Sales (qty)"),St=t("br",null,null,-1),Jt=a(" Today "),Tt=t("br",null,null,-1),wt=a(" Yesterday"),Bt=t("br",null,null,-1),Pt=a(" Last 7 Days "),Ot=a(" Status "),Lt=a(" Temp2 "),Dt=t("br",null,null,-1),$t=a(" (Evap)"),It=t("br",null,null,-1),jt=a(" \u2103 "),Et=a(" Postcode "),Nt=a(" Firmware Ver "),Kt=a(" Serial Num "),Ft={class:"bg-white"},Mt={key:0},Ut=t("br",null,null,-1),Ht={key:1},Gt={class:"flex flex-col items-center"},At=["onClick"],qt={class:"mt-1"},Yt=["onClick"],Rt={class:"text-blue-600"},Zt={class:"flex flex-col"},zt={class:"font-bold"},Qt=t("br",null,null,-1),Wt=t("br",null,null,-1),Xt=t("br",null,null,-1),eo=t("br",null,null,-1),to={class:"flex flex-col space-y-1"},oo={class:"flex flex-col"},so={class:"font-bold"},no={key:0},lo={class:"flex flex-col"},ao=t("span",{class:"font-bold"}," Drop Sensor ",-1),ro={key:1,class:"inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full bg-green-200"},io={class:"flex flex-col"},uo=t("span",{class:"font-bold"}," Fan Speed ",-1),co={class:"flex flex-col"},mo=t("span",{class:"font-bold"}," Door ",-1),po={class:"flex flex-col"},_o=t("span",{class:"font-bold"}," Coin ",-1),go={class:"flex flex-col items-center space-y-1"},fo=["onClick"],ho=["onClick"],xo=["onClick"],bo={key:0,class:"text-blue-600"},yo=t("br",null,null,-1),vo={key:0},ko={class:"flex justify-center space-x-1"},Co=t("span",null," Edit ",-1),Vo={key:0},So=t("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),Jo=[So],qo={__name:"Index",props:{categories:Object,categoryGroups:Object,constTempError:Number,operatorOptions:Object,vends:Object,vendChannelErrors:Object},setup(d){const T=d,o=h({codes:"",channel_codes:"",serialNum:"",customer_code:"",customer_name:"",categories:[],categoryGroups:[],errors:[],operator:"",is_binded_customer:"",tempHigherThan:"",tempDeltaHigherThan:"",vend_channel_error_id:"",is_online:"",is_sensor:"",is_door_open:"",fanSpeedLowerThan:"",sortKey:"",sortBy:!1,numberPerPage:""}),S=h([]),A=h([]),q=h([]),E=h([]),N=h([]),w=h(!1),K=h([]),F=h([]),B=h(!1),P=h(!1),z=h(""),O=h(),M=h([]),k=ie().props.value.auth.operatorRole,Y=h(I().format("HH:mm:ss"));de(()=>{M.value=[{id:"errors_only",desc:"Errors Only"},...T.vendChannelErrors.data],K.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],o.value.vend_channel_error_id=M.value[0],o.value.numberPerPage=K.value[0],A.value=T.categories.data.map(u=>({id:u.id,name:u.name})),q.value=T.categoryGroups.data.map(u=>({id:u.id,name:u.name})),S.value=[{id:"all",value:"All"},{id:"true",value:"Yes"},{id:"false",value:"No"}],N.value=[{id:"all",value:"All"},{id:"true",value:"Enabled"},{id:"false",value:"Disabled"}],E.value=[{id:"all",value:"All"},{id:"open",value:"Open"},{id:"close",value:"Close"}],F.value=[{id:"all",full_name:"All"},...T.operatorOptions.data.map(u=>({id:u.id,full_name:u.full_name}))],o.value.is_door_open=E.value[0],o.value.is_online=S.value[0],o.value.is_sensor=N.value[0],o.value.is_binded_customer=k.value?S.value[0]:S.value[1],o.value.operator=F.value[0]});function Q(u){O.value=u,B.value=!0}function W(){B.value=!1}function X(u){O.value=u,P.value=!0}function ee(){P.value=!1}function U(){G.Inertia.get("/vends",{...o.value,categories:o.value.categories.map(u=>u.id),categoryGroups:o.value.categoryGroups.map(u=>u.id),errors:o.value.errors.map(u=>u.id),operator_id:o.value.operator.id,is_binded_customer:o.value.is_binded_customer.id,is_door_open:o.value.is_door_open.id,is_online:o.value.is_online.id,is_sensor:o.value.is_sensor.id,numberPerPage:o.value.numberPerPage.id},{preserveState:!0,replace:!0,onFinish:u=>{Y.value=I().format("HH:mm:ss")}})}function L(u,s){G.Inertia.get("/vends/"+u+"/temp/"+s)}function te(){G.Inertia.get("/vends")}function C(u){o.value.sortKey=u,o.value.sortBy=!o.value.sortBy,U()}function oe(){w.value=!0,axios({method:"post",url:"/vends/channels/excel",params:{...o.value,categories:o.value.categories.map(u=>u.id),categoryGroups:o.value.categoryGroups.map(u=>u.id),errors:o.value.errors.map(u=>u.id),operator_id:o.value.operator.id,is_binded_customer:o.value.is_binded_customer.id,is_door_open:o.value.is_door_open.id,is_online:o.value.is_online.id,is_sensor:o.value.is_sensor.id},responseType:"blob"}).then(u=>{fileDownload(u.data,"Vending_Channels_"+I().format("YYMMDDhhmmss")+".xlsx")}).catch(u=>{console.log(u)}).finally(()=>{w.value=!1})}return(u,s)=>(i(),m(j,null,[n(x(ue),{title:"Vending Machines"}),n(se,null,{header:r(()=>[_e]),default:r(()=>{var R,Z;return[t("div",ge,[t("div",fe,[t("div",he,[n(y,{placeholderStr:"Vend ID",modelValue:o.value.codes,"onUpdate:modelValue":s[0]||(s[0]=e=>o.value.codes=e)},{default:r(()=>[xe,be]),_:1},8,["modelValue"]),n(y,{placeholderStr:"Channel ID",modelValue:o.value.channel_codes,"onUpdate:modelValue":s[1]||(s[1]=e=>o.value.channel_codes=e)},{default:r(()=>[ye,ve]),_:1},8,["modelValue"]),n(y,{placeholderStr:"Serial Num",modelValue:o.value.serialNum,"onUpdate:modelValue":s[2]||(s[2]=e=>o.value.serialNum=e)},{default:r(()=>[ke]),_:1},8,["modelValue"]),n(y,{placeholderStr:"Number",modelValue:o.value.tempHigherThan,"onUpdate:modelValue":s[3]||(s[3]=e=>o.value.tempHigherThan=e)},{default:r(()=>[Ce]),_:1},8,["modelValue"]),n(y,{placeholderStr:"Number",modelValue:o.value.tempDeltaHigherThan,"onUpdate:modelValue":s[4]||(s[4]=e=>o.value.tempDeltaHigherThan=e)},{default:r(()=>[Ve]),_:1},8,["modelValue"]),t("div",null,[Se,n(b,{modelValue:o.value.errors,"onUpdate:modelValue":s[5]||(s[5]=e=>o.value.errors=e),options:M.value,valueProp:"id",label:"desc",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),x(k)?p("",!0):(i(),J(y,{key:0,placeholderStr:"Cust ID",modelValue:o.value.customer_code,"onUpdate:modelValue":s[6]||(s[6]=e=>o.value.customer_code=e)},{default:r(()=>[Je]),_:1},8,["modelValue"])),x(k)?p("",!0):(i(),J(y,{key:1,placeholderStr:"Cust Name",modelValue:o.value.customer_name,"onUpdate:modelValue":s[7]||(s[7]=e=>o.value.customer_name=e)},{default:r(()=>[Te]),_:1},8,["modelValue"])),x(k)?p("",!0):(i(),m("div",we,[Be,n(b,{modelValue:o.value.categories,"onUpdate:modelValue":s[8]||(s[8]=e=>o.value.categories=e),options:A.value,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])),x(k)?p("",!0):(i(),m("div",Pe,[Oe,n(b,{modelValue:o.value.categoryGroups,"onUpdate:modelValue":s[9]||(s[9]=e=>o.value.categoryGroups=e),options:q.value,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])),t("div",null,[Le,n(b,{modelValue:o.value.is_online,"onUpdate:modelValue":s[10]||(s[10]=e=>o.value.is_online=e),options:S.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),x(k)?p("",!0):(i(),m("div",De,[$e,n(b,{modelValue:o.value.is_binded_customer,"onUpdate:modelValue":s[11]||(s[11]=e=>o.value.is_binded_customer=e),options:S.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])),t("div",null,[Ie,n(b,{modelValue:o.value.is_sensor,"onUpdate:modelValue":s[12]||(s[12]=e=>o.value.is_sensor=e),options:N.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),t("div",null,[je,n(b,{modelValue:o.value.is_door_open,"onUpdate:modelValue":s[13]||(s[13]=e=>o.value.is_door_open=e),options:E.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),n(y,{placeholderStr:"Fan Speed",modelValue:o.value.fanSpeedLowerThan,"onUpdate:modelValue":s[14]||(s[14]=e=>o.value.fanSpeedLowerThan=e)},{default:r(()=>[Ee]),_:1},8,["modelValue"]),x(k)?p("",!0):(i(),m("div",Ne,[Ke,n(b,{modelValue:o.value.operator,"onUpdate:modelValue":s[15]||(s[15]=e=>o.value.operator=e),options:F.value,trackBy:"id",valueProp:"id",label:"full_name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]))]),t("div",Fe,[t("div",Me,[t("div",Ue,[n($,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[16]||(s[16]=e=>U())},{default:r(()=>[n(x(ae),{class:"h-4 w-4","aria-hidden":"true"}),He]),_:1}),n($,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[17]||(s[17]=e=>te())},{default:r(()=>[n(x(ce),{class:"h-4 w-4","aria-hidden":"true"}),Ge]),_:1}),n($,{class:"inline-flex space-x-1 items-center rounded-md border border-gray-500 bg-white px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[18]||(s[18]=e=>oe())},{default:r(()=>[w.value?p("",!0):(i(),J(x(me),{key:0,class:"h-4 w-4","aria-hidden":"true"})),w.value?(i(),m("svg",Ae,Re)):p("",!0),Ze]),_:1})])]),t("div",ze,[t("span",Qe,[t("p",null,"Last loaded: "+l(Y.value),1)]),t("p",We,[Xe,t("span",et,l((R=d.vends.meta.from)!=null?R:0),1),tt,t("span",ot,l((Z=d.vends.meta.to)!=null?Z:0),1),st,t("span",nt,l(d.vends.meta.total),1),lt]),n(b,{modelValue:o.value.numberPerPage,"onUpdate:modelValue":s[19]||(s[19]=e=>o.value.numberPerPage=e),options:K.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:U},null,8,["modelValue","options"])])])]),t("div",at,[t("div",rt,[t("div",it,[t("table",dt,[t("thead",ut,[t("tr",ct,[n(v,null,{default:r(()=>[mt]),_:1}),n(V,{modelName:"vends.code",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:s[20]||(s[20]=e=>C("vends.code"))},{default:r(()=>[pt]),_:1},8,["sortKey","sortBy"]),n(v,null,{default:r(()=>[_t]),_:1}),n(V,{modelName:"temp",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:s[21]||(s[21]=e=>C("temp"))},{default:r(()=>[gt,ft,ht]),_:1},8,["sortKey","sortBy"]),n(v,null,{default:r(()=>[xt,bt,yt]),_:1}),n(v,null,{default:r(()=>[vt]),_:1}),n(V,{modelName:"vend_channel_totals_json->balancePercent",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:s[22]||(s[22]=e=>C("vend_channel_totals_json->balancePercent"))},{default:r(()=>[kt]),_:1},8,["sortKey","sortBy"]),n(V,{modelName:"vend_channel_totals_json->outOfStockSkuPercent",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:s[23]||(s[23]=e=>C("vend_channel_totals_json->outOfStockSkuPercent"))},{default:r(()=>[Ct]),_:1},8,["sortKey","sortBy"]),n(V,{modelName:"vend_transaction_totals_json->seven_days_amount",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:s[24]||(s[24]=e=>C("vend_transaction_totals_json->seven_days_amount"))},{default:r(()=>[Vt,St,Jt,Tt,wt,Bt,Pt]),_:1},8,["sortKey","sortBy"]),n(v,null,{default:r(()=>[Ot]),_:1}),n(V,{modelName:"parameter_json->t2",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:s[25]||(s[25]=e=>C("parameter_json->t2"))},{default:r(()=>[Lt,Dt,$t,It,jt]),_:1},8,["sortKey","sortBy"]),n(V,{modelName:"postcode",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:s[26]||(s[26]=e=>C("postcode"))},{default:r(()=>[Et]),_:1},8,["sortKey","sortBy"]),n(v,null,{default:r(()=>[Nt]),_:1}),n(v,null,{default:r(()=>[Kt]),_:1}),n(v)])]),t("tbody",Ft,[(i(!0),m(j,null,H(d.vends.data,(e,g)=>(i(),m("tr",{key:g,class:"divide-x divide-gray-200"},[n(f,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-center"},{default:r(()=>[a(l(d.vends.meta.from+g),1)]),_:2},1032,["currentIndex","totalLength"]),n(f,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-center"},{default:r(()=>[a(l(e.code),1)]),_:2},1032,["currentIndex","totalLength"]),n(f,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-left"},{default:r(()=>[e.latestVendBinding&&e.latestVendBinding.customer?(i(),m("span",Mt,[a(l(e.latestVendBinding.customer.code)+" ",1),Ut,a(" "+l(e.latestVendBinding.customer.name),1)])):(i(),m("span",Ht,l(e.name),1))]),_:2},1032,["currentIndex","totalLength"]),n(f,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-center"},{default:r(()=>[t("div",Gt,[t("button",{type:"button",class:_(["inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-4/5 text-right justify-center",[e.is_online?e.temp>-15?"bg-red-400 active:bg-red-500 hover:bg-red-600":"bg-green-400 active:bg-green-500 hover:bg-green-600":"bg-gray-300 active:bg-gray-500 hover:bg-gray-600"]]),onClick:c=>L(e.id,1)},l(e.is_temp_error?"Error":e.temp),11,At),t("span",qt,l(e.temp_updated_at),1),e.parameterJson&&e.parameterJson.t2&&e.parameterJson.t2!=d.constTempError&&!e.is_temp_error?(i(),m("span",{key:0,class:_(["mt-1",(e.temp-e.parameterJson.t2/10).toFixed(1)>=4?"text-red-700":"text-green-700"])},l((e.temp-e.parameterJson.t2/10).toFixed(1)),3)):p("",!0)])]),_:2},1032,["currentIndex","totalLength"]),n(f,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-left"},{default:r(()=>[e.vendChannelsJson?(i(),m("ul",{key:0,class:"sm:grid sm:grid-cols-[105px_minmax(110px,_1fr)_100px] hover:cursor-pointer",onClick:c=>Q(e)},[(i(!0),m(j,null,H(e.vendChannelsJson,(c,D)=>(i(),m("li",{class:_(["quick-look",[D>0&&String(c.code)[0]!==String(e.vendChannelsJson[D-1].code)[0]?"col-start-1":""]])},[t("span",{class:_([D>0&&String(c.code)[0]!==String(e.vendChannelsJson[D-1].code)[0]?"border-t-4 pt-1":""])},[t("span",null," #"+l(c.code)+", ",1),t("span",Rt,l(c.capacity-c.qty)+", ",1),t("span",{class:_([c.qty<=2?"text-red-700":"text-green-700"])},l(c.qty)+"/"+l(c.capacity),3)],2)],2))),256))],8,Yt)):p("",!0)]),_:2},1032,["currentIndex","totalLength"]),n(f,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-center"},{default:r(()=>[(i(!0),m(j,null,H(e.vendChannelErrorLogsJson,c=>(i(),m("span",{class:_(["inline-flex items-center rounded px-2.5 py-0.5 text-xs font-medium border",[c.vendChannelError?c.vendChannelError.code==4||c.vendChannelError.code==5||c.vendChannelError.code==7?"bg-blue-100 text-blue-800":"bg-red-100 text-red-800":c.vend_channel.code==4||c.vend_channel.code==5||c.vend_channel.code==7?"bg-blue-100 text-blue-800":"bg-red-100 text-red-800"]])},[t("div",Zt,[t("div",null,[a(" #"+l(c.vendChannel?c.vendChannel.code:c.vend_channel.code)+", ",1),t("span",zt," ("+l(c.vendChannelError?c.vendChannelError.code:c.vend_channel_error.code)+") ",1)]),t("div",null,l(c.created_at),1)])],2))),256))]),_:2},1032,["currentIndex","totalLength"]),n(f,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-center"},{default:r(()=>[e.vendChannelTotalsJson?(i(),m("span",{key:0,class:_([e.vendChannelTotalsJson.balancePercent<=30?"text-red-700":e.vendChannelTotalsJson.balancePercent>60?"":"text-blue-700"])},[a(l(e.vendChannelTotalsJson.qty)+"/ "+l(e.vendChannelTotalsJson.capacity)+" ",1),Qt,a(" ("+l(e.vendChannelTotalsJson.balancePercent)+"%) ",1)],2)):p("",!0)]),_:2},1032,["currentIndex","totalLength"]),n(f,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-center"},{default:r(()=>[e.vendChannelTotalsJson?(i(),m("span",{key:0,class:_([e.vendChannelTotalsJson.outOfStockSkuPercent>40?"text-red-700":""])},[a(l(e.vendChannelTotalsJson.outOfStockSku)+"/ "+l(e.vendChannelTotalsJson.count)+" ",1),Wt,a(" ("+l(e.vendChannelTotalsJson.outOfStockSkuPercent)+"%) ",1)],2)):p("",!0)]),_:2},1032,["currentIndex","totalLength"]),n(f,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-center"},{default:r(()=>[t("span",{class:_([e.vendTransactionTotalsJson.today_amount/100>=30?"text-green-700":"text-red-700"])},[a(l((e.vendTransactionTotalsJson.today_amount/100).toLocaleString(void 0,{minimumFractionDigits:2}))+" ("+l(e.vendTransactionTotalsJson.today_count.toLocaleString(void 0,{minimumFractionDigits:0}))+") ",1),Xt],2),t("span",{class:_([e.vendTransactionTotalsJson.yesterday_amount/100>=30?"text-green-700":"text-red-700"])},[a(l((e.vendTransactionTotalsJson.yesterday_amount/100).toLocaleString(void 0,{minimumFractionDigits:2}))+" ("+l(e.vendTransactionTotalsJson.yesterday_count.toLocaleString(void 0,{minimumFractionDigits:0}))+") ",1),eo],2),t("span",{class:_([e.vendTransactionTotalsJson.seven_days_amount/100>200?"text-green-700":"text-red-700"])},l((e.vendTransactionTotalsJson.seven_days_amount/100).toLocaleString(void 0,{minimumFractionDigits:2}))+"("+l(e.vendTransactionTotalsJson.seven_days_count.toLocaleString(void 0,{minimumFractionDigits:0}))+") ",3)]),_:2},1032,["currentIndex","totalLength"]),n(f,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-center"},{default:r(()=>[t("div",to,[t("div",{class:_(["inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full",[e.is_online?"bg-green-200":"bg-red-200"]])},[t("div",oo,[t("span",so,l(e.is_online?"Online":"Offline"),1),e.last_updated_at?(i(),m("span",no,l(e.last_updated_at),1)):p("",!0)])],2),e.parameterJson?(i(),m("div",{key:0,class:_(["inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full",[e.parameterJson.Sensor%2==0?"bg-red-200":"bg-green-200"]])},[t("div",lo,[ao,t("span",null,l(e.parameterJson.Sensor%2==0?"Disabled":"Enabled"),1)])],2)):p("",!0),e.parameterJson&&e.parameterJson.fan?(i(),m("div",ro,[t("div",io,[uo,t("span",null,l(e.parameterJson.fan),1)])])):p("",!0),e.parameterJson&&e.parameterJson.door?(i(),m("div",{key:2,class:_(["inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full",[e.parameterJson.door=="close"?"bg-green-200":"bg-red-200"]])},[t("div",co,[mo,t("span",null,l(e.parameterJson.door=="open"?"Open":"Close"),1)])],2)):p("",!0),e.parameterJson&&e.parameterJson.CoinCnt?(i(),m("div",{key:3,class:_(["inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full",[e.parameterJson.CoinCnt>1600?"bg-green-200":"bg-red-200"]])},[t("div",po,[_o,t("span",null,l((e.parameterJson.CoinCnt/100).toFixed(2)),1)])],2)):p("",!0)])]),_:2},1032,["currentIndex","totalLength"]),n(f,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-center"},{default:r(()=>[t("div",go,[e.parameterJson&&"t2"in e.parameterJson?(i(),m("button",{key:0,type:"button",class:_(["inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-4/5 text-right justify-center",[e.is_online?e.temp>-15||e.parameterJson.t2==d.constTempError?"bg-red-400 active:bg-red-500 hover:bg-red-600":"bg-green-400 active:bg-green-500 hover:bg-green-600":"bg-gray-300 active:bg-gray-500 hover:bg-gray-600"]]),onClick:c=>L(e.id,2)},l(e.parameterJson.t2==d.constTempError?"Error":e.parameterJson.t2/10)+"(t2) ",11,fo)):p("",!0),e.parameterJson&&e.parameterJson.t3&&e.parameterJson.t3!=d.constTempError?(i(),m("button",{key:1,type:"button",class:_(["inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-4/5 text-right justify-center",[e.is_online?e.temp>-15||e.parameterJson.t3==d.constTempError?"bg-red-400 active:bg-red-500 hover:bg-red-600":"bg-green-400 active:bg-green-500 hover:bg-green-600":"bg-gray-300 active:bg-gray-500 hover:bg-gray-600"]]),onClick:c=>L(e.id,3)},l(e.parameterJson.t3==d.constTempError?"Error":e.parameterJson.t3/10)+"(t3) ",11,ho)):p("",!0),e.parameterJson&&e.parameterJson.t4&&e.parameterJson.t4!=d.constTempError?(i(),m("button",{key:2,type:"button",class:_(["inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-4/5 text-right justify-center",[e.is_online?e.temp>-15||e.parameterJson.t4==d.constTempError?"bg-red-400 active:bg-red-500 hover:bg-red-600":"bg-green-400 active:bg-green-500 hover:bg-green-600":"bg-gray-300 active:bg-gray-500 hover:bg-gray-600"]]),onClick:c=>L(e.id,4)},l(e.parameterJson.t4==d.constTempError?"Error":e.parameterJson.t4/10)+"(t4) ",11,xo)):p("",!0)])]),_:2},1032,["currentIndex","totalLength"]),n(f,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-center"},{default:r(()=>[a(l(e.latestVendBinding&&e.latestVendBinding.customer&&e.latestVendBinding.customer.deliveryAddress?e.latestVendBinding.customer.deliveryAddress.postcode:null),1)]),_:2},1032,["currentIndex","totalLength"]),n(f,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-center"},{default:r(()=>[a(l(e.parameterJson.Ver?e.parameterJson.Ver.toString(16):null)+" ",1),e.apkVerJson&&"apkver"in e.apkVerJson?(i(),m("span",bo,[yo,a("Apk: "+l(e.apkVerJson.apkver)+" ",1),e.apkVerJson&&"buildtime"in e.apkVerJson?(i(),m("span",vo,l(x(I)(new Date(e.apkVerJson.buildtime)).format("YYMMDD HH:mm:ss")),1)):p("",!0)])):p("",!0)]),_:2},1032,["currentIndex","totalLength"]),n(f,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-center"},{default:r(()=>[a(l(e.serial_num),1)]),_:2},1032,["currentIndex","totalLength"]),n(f,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-center"},{default:r(()=>[t("div",ko,[n($,{type:"button",class:"bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1",onClick:c=>X(e)},{default:r(()=>[n(x(pe),{class:"w-4 h-4"}),Co]),_:2},1032,["onClick"])])]),_:2},1032,["currentIndex","totalLength"])]))),128)),d.vends.data.length?p("",!0):(i(),m("tr",Vo,Jo))])])]),d.vends.data.length?(i(),J(re,{key:0,links:d.vends.links,meta:d.vends.meta},null,8,["links","meta"])):p("",!0)])])]),B.value?(i(),J(ne,{key:0,vend:O.value,showModal:B.value,onModalClose:W},null,8,["vend","showModal"])):p("",!0),P.value?(i(),J(le,{key:1,vend:O.value,type:z.value,showModal:P.value,onModalClose:ee},null,8,["vend","type","showModal"])):p("",!0)]}),_:1})],64))}};export{qo as default};
