import{_ as D}from"./Authenticated.f988114b.js";import{_ as E}from"./Button.eb6f7716.js";import{r as G,a as F,T as x,_ as b,b as H,c as g}from"./TableHeadSort.c6a785b0.js";import{_ as V}from"./SearchInput.4fea281c.js";import{_ as v}from"./MultiSelect.58e29d90.js";import{i as h,j as q,o as i,g as m,a as o,b as w,w as l,F as k,H as M,d as t,t as a,m as L,p,c as R,f as r,J as I,n as f}from"./app.c77c62cf.js";import"./open-closed.898b8070.js";import"./use-resolve-button-type.5b79fd0e.js";import"./RectangleStackIcon.ec8888fa.js";import"./_plugin-vue_export-helper.cdc0426e.js";const z=t("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Vending Machines (List) ",-1),Y={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},Q={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},W={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},X=r(" Vend ID "),Z=r(" Serial Num "),ee=r(" Temp >> "),te=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Errors? ",-1),ne=r(" Cust ID "),oe=r(" Cust ID Name "),se=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Category ",-1),le=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Group ",-1),ae=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Is Online? ",-1),re=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Country ",-1),de={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},ie={class:"mt-3"},ue={class:"flex space-x-1"},ce=t("span",null," Search ",-1),me=t("span",null," Reset ",-1),_e={class:"flex flex-col space-y-2"},ge={class:"text-sm text-gray-700 leading-5 flex space-x-1"},pe=t("span",null,"Showing",-1),fe={class:"font-medium"},xe=t("span",null,"to",-1),he={class:"font-medium"},ye=t("span",null,"of",-1),be={class:"font-medium"},ve=t("span",null,"results",-1),Ve={class:"mt-6 flex flex-col"},Ce={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},ke={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},Be={class:"min-w-full border-separate",style:{"border-spacing":"0"}},Se={class:"bg-gray-100"},Je={class:"divide-x divide-gray-200"},Te=r(" # "),Pe=r(" ID "),we=r(" Name "),Le=r(" Category "),Ie=r(" Errors "),Ne=r(" Inventory Status "),Oe=r(" Temp "),je=t("br",null,null,-1),Ke=r(" (Chamber) "),$e=r(" Temp "),Ue=t("br",null,null,-1),Ee=r(" (Evaporator) "),Ae=r(" Balance Stock "),De=r(" Out of Stock SKU "),Ge=r(" Status "),Fe=r(" Serial Num "),He=r(" Postcode "),qe=r(" Firmware Ver "),Me={class:"bg-white"},Re=t("br",null,null,-1),ze=t("br",null,null,-1),Ye={class:"flex flex-col"},Qe={class:"font-bold"},We={class:"grid grid-cols-[105px_minmax(110px,_1fr)_100px]"},Xe={class:"font-semibold"},Ze={class:"text-blue-600 text-sm"},et={class:"flex flex-col items-center"},tt=["onClick"],nt={class:"mt-1"},ot={class:"flex flex-col items-center"},st=["onClick"],lt=t("br",null,null,-1),at=t("br",null,null,-1),rt={class:"grid grid-cols-[90px_minmax(90px,_1fr)_90px] gap-1"},dt={class:"flex flex-col"},it={class:"font-bold"},ut={key:0},ct={class:"flex flex-col"},mt=t("span",{class:"font-bold"}," Drop Sensor ",-1),_t={key:1,class:"inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full bg-green-200"},gt={class:"flex flex-col"},pt=t("span",{class:"font-bold"}," Fan Speed ",-1),ft={class:"flex flex-col"},xt=t("span",{class:"font-bold"}," Door ",-1),ht={class:"flex flex-col"},yt=t("span",{class:"font-bold"}," Coin ",-1),bt={key:0},vt={key:0},Vt=t("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),Ct=[Vt],Ot={__name:"Index",props:{categories:Object,categoryGroups:Object,constTempError:Number,countries:Object,vends:Object,vendChannelErrors:Object},setup(d){const C=d,n=h({code:"",serialNum:"",customer_code:"",customer_name:"",categories:[],categoryGroups:[],country_id:"",tempHigherThan:"",vend_channel_error_id:"",is_online:"",sortKey:"",sortBy:!0,numberPerPage:""}),B=h([]),S=h([]),N=h([]),O=h([]),J=h([]),T=h([]);q(()=>{B.value=[{id:"",desc:"All"},{id:"errors_only",desc:"Errors Only"},...C.vendChannelErrors.data],S.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],n.value.vend_channel_error_id=B.value[0],n.value.numberPerPage=S.value[0],N.value=C.categories.data.map(u=>({id:u.id,name:u.name})),O.value=C.categoryGroups.data.map(u=>({id:u.id,name:u.name})),T.value=[{id:"all",value:"All"},{id:"true",value:"Yes"},{id:"false",value:"No"}],n.value.is_online=T.value[1],J.value=[{id:"0",name:"All"},...C.countries.data.map(u=>({id:u.id,name:u.name}))],n.value.country_id=J.value[1]});function P(){I.Inertia.get("/vends",{...n.value,vend_channel_error_id:n.value.vend_channel_error_id.id,categories:n.value.categories.map(u=>u.id),categoryGroups:n.value.categoryGroups.map(u=>u.id),country_id:n.value.country_id.id,is_online:n.value.is_online.id,numberPerPage:n.value.numberPerPage.id},{preserveState:!0,replace:!0})}function j(u,s){I.Inertia.get("/vends/"+u+"/temp/"+s)}function A(){I.Inertia.get("/vends")}function y(u){n.value.sortKey=u,n.value.sortBy=!n.value.sortBy,P()}return(u,s)=>(i(),m(k,null,[o(w(M),{title:"Vending Machines"}),o(D,null,{header:l(()=>[z]),default:l(()=>{var K,$;return[t("div",Y,[t("div",Q,[t("div",W,[o(V,{placeholderStr:"Code",modelValue:n.value.code,"onUpdate:modelValue":s[0]||(s[0]=e=>n.value.code=e)},{default:l(()=>[X]),_:1},8,["modelValue"]),o(V,{placeholderStr:"Serial Num",modelValue:n.value.serialNum,"onUpdate:modelValue":s[1]||(s[1]=e=>n.value.serialNum=e)},{default:l(()=>[Z]),_:1},8,["modelValue"]),o(V,{placeholderStr:"Number",modelValue:n.value.tempHigherThan,"onUpdate:modelValue":s[2]||(s[2]=e=>n.value.tempHigherThan=e)},{default:l(()=>[ee]),_:1},8,["modelValue"]),t("div",null,[te,o(v,{modelValue:n.value.vend_channel_error_id,"onUpdate:modelValue":s[3]||(s[3]=e=>n.value.vend_channel_error_id=e),options:B.value,trackBy:"id",valueProp:"id",label:"desc",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),o(V,{placeholderStr:"Cust ID",modelValue:n.value.customer_code,"onUpdate:modelValue":s[4]||(s[4]=e=>n.value.customer_code=e)},{default:l(()=>[ne]),_:1},8,["modelValue"]),o(V,{placeholderStr:"Cust ID Name",modelValue:n.value.customer_name,"onUpdate:modelValue":s[5]||(s[5]=e=>n.value.customer_name=e)},{default:l(()=>[oe]),_:1},8,["modelValue"]),t("div",null,[se,o(v,{modelValue:n.value.categories,"onUpdate:modelValue":s[6]||(s[6]=e=>n.value.categories=e),options:N.value,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),t("div",null,[le,o(v,{modelValue:n.value.categoryGroups,"onUpdate:modelValue":s[7]||(s[7]=e=>n.value.categoryGroups=e),options:O.value,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),t("div",null,[ae,o(v,{modelValue:n.value.is_online,"onUpdate:modelValue":s[8]||(s[8]=e=>n.value.is_online=e),options:T.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),t("div",null,[re,o(v,{modelValue:n.value.country_id,"onUpdate:modelValue":s[9]||(s[9]=e=>n.value.country_id=e),options:J.value,trackBy:"id",valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])]),t("div",de,[t("div",ie,[t("div",ue,[o(E,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[10]||(s[10]=e=>P())},{default:l(()=>[o(w(G),{class:"h-4 w-4","aria-hidden":"true"}),ce]),_:1}),o(E,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[11]||(s[11]=e=>A())},{default:l(()=>[o(w(F),{class:"h-4 w-4","aria-hidden":"true"}),me]),_:1})])]),t("div",_e,[t("p",ge,[pe,t("span",fe,a((K=d.vends.meta.from)!=null?K:0),1),xe,t("span",he,a(($=d.vends.meta.to)!=null?$:0),1),ye,t("span",be,a(d.vends.meta.total),1),ve]),o(v,{modelValue:n.value.numberPerPage,"onUpdate:modelValue":s[12]||(s[12]=e=>n.value.numberPerPage=e),options:S.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:P},null,8,["modelValue","options"])])])]),t("div",Ve,[t("div",Ce,[t("div",ke,[t("table",Be,[t("thead",Se,[t("tr",Je,[o(x,null,{default:l(()=>[Te]),_:1}),o(b,{modelName:"code",sortKey:n.value.sortKey,sortBy:n.value.sortBy,onSortTable:s[13]||(s[13]=e=>y("code"))},{default:l(()=>[Pe]),_:1},8,["sortKey","sortBy"]),o(x,null,{default:l(()=>[we]),_:1}),o(x,null,{default:l(()=>[Le]),_:1}),o(x,null,{default:l(()=>[Ie]),_:1}),o(x,null,{default:l(()=>[Ne]),_:1}),o(b,{modelName:"temp",sortKey:n.value.sortKey,sortBy:n.value.sortBy,onSortTable:s[14]||(s[14]=e=>y("temp"))},{default:l(()=>[Oe,je,Ke]),_:1},8,["sortKey","sortBy"]),o(b,{modelName:"parameter_json->t2",sortKey:n.value.sortKey,sortBy:n.value.sortBy,onSortTable:s[15]||(s[15]=e=>y("parameter_json->t2"))},{default:l(()=>[$e,Ue,Ee]),_:1},8,["sortKey","sortBy"]),o(b,{modelName:"vend_channel_totals_json->balancePercent",sortKey:n.value.sortKey,sortBy:n.value.sortBy,onSortTable:s[16]||(s[16]=e=>y("vend_channel_totals_json->balancePercent"))},{default:l(()=>[Ae]),_:1},8,["sortKey","sortBy"]),o(b,{modelName:"vend_channel_totals_json->outOfStockSkuPercent",sortKey:n.value.sortKey,sortBy:n.value.sortBy,onSortTable:s[17]||(s[17]=e=>y("vend_channel_totals_json->outOfStockSkuPercent"))},{default:l(()=>[De]),_:1},8,["sortKey","sortBy"]),o(x,null,{default:l(()=>[Ge]),_:1}),o(x,null,{default:l(()=>[Fe]),_:1}),o(b,{modelName:"latestVendBinding.customer.deliveryAddress.postcode",sortKey:n.value.sortKey,sortBy:n.value.sortBy,onSortTable:s[18]||(s[18]=e=>y("latestVendBinding.customer.deliveryAddress.postcode"))},{default:l(()=>[He]),_:1},8,["sortKey","sortBy"]),o(x,null,{default:l(()=>[qe]),_:1})])]),t("tbody",Me,[(i(!0),m(k,null,L(d.vends.data,(e,_)=>(i(),m("tr",{key:e.id,class:"divide-x divide-gray-200"},[o(g,{currentIndex:_,totalLength:d.vends.length,inputClass:"text-center"},{default:l(()=>[r(a(d.vends.meta.from+_),1)]),_:2},1032,["currentIndex","totalLength"]),o(g,{currentIndex:_,totalLength:d.vends.length,inputClass:"text-center"},{default:l(()=>[r(a(e.code),1)]),_:2},1032,["currentIndex","totalLength"]),o(g,{currentIndex:_,totalLength:d.vends.length,inputClass:"text-left"},{default:l(()=>[r(a(e.latestVendBinding&&e.latestVendBinding.customer?e.latestVendBinding.customer.code:null)+" ",1),Re,r(" "+a(e.latestVendBinding&&e.latestVendBinding.customer?e.latestVendBinding.customer.name:null),1)]),_:2},1032,["currentIndex","totalLength"]),o(g,{currentIndex:_,totalLength:d.vends.length,inputClass:"text-left"},{default:l(()=>[r(a(e.latestVendBinding&&e.latestVendBinding.customer&&e.latestVendBinding.customer.category?e.latestVendBinding.customer.category.name:null)+" ",1),ze,r(" "+a(e.latestVendBinding&&e.latestVendBinding.customer&&e.latestVendBinding.customer.category&&e.latestVendBinding.customer.category.category_group?e.latestVendBinding.customer.category.category_group.name:null),1)]),_:2},1032,["currentIndex","totalLength"]),o(g,{currentIndex:_,totalLength:d.vends.length,inputClass:"text-center"},{default:l(()=>[(i(!0),m(k,null,L(e.vendChannelErrorLogsJson,c=>(i(),m("span",{class:f(["inline-flex items-center rounded px-2.5 py-0.5 text-xs font-medium border",[c.is_error_cleared?"bg-green-100 text-green-800":"bg-red-100 text-red-800"]])},[t("div",Ye,[t("div",null,[r(" #"+a(c.vend_channel.code)+", ",1),t("span",Qe," ("+a(c.vend_channel_error.code)+") ",1)]),t("div",null,a(c.created_at),1)])],2))),256))]),_:2},1032,["currentIndex","totalLength"]),o(g,{currentIndex:_,totalLength:d.vends.length,inputClass:"text-center"},{default:l(()=>[t("div",We,[(i(!0),m(k,null,L(e.vendChannelsJson.filter(c=>c.code>=10&&c.code<=69),(c,U)=>(i(),m("span",{class:f(["inline-flex justify-evenly items-center rounded px-1 py-0.5 text-xs font-medium border min-w-full",[U>0&&String(c.code)[0]!==String(e.vendChannelsJson[U-1].code)[0]?"col-start-1":""]])},[t("div",Xe," #"+a(c.code)+", ",1),t("div",Ze,a(c.capacity-c.qty)+", ",1),t("div",{class:f([c.qty<=2?"text-red-700":"text-green-700"])},a(c.qty)+"/"+a(c.capacity),3)],2))),256))])]),_:2},1032,["currentIndex","totalLength"]),o(g,{currentIndex:_,totalLength:d.vends.length,inputClass:"text-center"},{default:l(()=>[t("div",et,[t("button",{type:"button",class:f(["inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-4/5 text-right justify-center",[e.temp>-15?"bg-red-400 active:bg-red-500 hover:bg-red-600":"bg-green-400 active:bg-green-500 hover:bg-green-600"]]),onClick:c=>j(e.id,1)},a(e.is_temp_error?"Error":e.temp),11,tt),t("span",nt,a(e.temp_updated_at),1)])]),_:2},1032,["currentIndex","totalLength"]),o(g,{currentIndex:_,totalLength:d.vends.length,inputClass:"text-center"},{default:l(()=>[t("div",ot,[e.parameterJson&&e.parameterJson.t2?(i(),m("button",{key:0,type:"button",class:f(["inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-4/5 text-right justify-center",[e.temp>-15||e.parameterJson.t2==d.constTempError?"bg-red-400 active:bg-red-500 hover:bg-red-600":"bg-green-400 active:bg-green-500 hover:bg-green-600"]]),onClick:c=>j(e.id,2)},a(e.parameterJson.t2==d.constTempError?"Error":e.parameterJson.t2/10),11,st)):p("",!0)])]),_:2},1032,["currentIndex","totalLength"]),o(g,{currentIndex:_,totalLength:d.vends.length,inputClass:"text-center"},{default:l(()=>[e.vendChannelTotalsJson?(i(),m("span",{key:0,class:f([e.vendChannelTotalsJson.balancePercent<=30?"text-red-700":e.vendChannelTotalsJson.balancePercent>60?"":"text-blue-700"])},[r(a(e.vendChannelTotalsJson.qty)+"/ "+a(e.vendChannelTotalsJson.capacity)+" ",1),lt,r(" ("+a(e.vendChannelTotalsJson.balancePercent)+"%) ",1)],2)):p("",!0)]),_:2},1032,["currentIndex","totalLength"]),o(g,{currentIndex:_,totalLength:d.vends.length,inputClass:"text-center"},{default:l(()=>[e.vendChannelTotalsJson?(i(),m("span",{key:0,class:f([e.vendChannelTotalsJson.outOfStockSkuPercent>40?"text-red-700":""])},[r(a(e.vendChannelTotalsJson.outOfStockSku)+"/ "+a(e.vendChannelTotalsJson.count)+" ",1),at,r(" ("+a(e.vendChannelTotalsJson.outOfStockSkuPercent)+"%) ",1)],2)):p("",!0)]),_:2},1032,["currentIndex","totalLength"]),o(g,{currentIndex:_,totalLength:d.vends.length,inputClass:"text-center"},{default:l(()=>[t("div",rt,[t("div",{class:f(["inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full",[e.is_online?"bg-green-200":"bg-red-200"]])},[t("div",dt,[t("span",it,a(e.is_online?"Online":"Offline"),1),e.last_updated_at?(i(),m("span",ut,a(e.last_updated_at),1)):p("",!0)])],2),e.parameterJson&&e.parameterJson.Sensor?(i(),m("div",{key:0,class:f(["inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full",[e.parameterJson.Sensor==1?"bg-green-200":"bg-red-200"]])},[t("div",ct,[mt,t("span",null,a(e.parameterJson.Sensor==1?"Active":"Inactive"),1)])],2)):p("",!0),e.parameterJson&&e.parameterJson.fan?(i(),m("div",_t,[t("div",gt,[pt,t("span",null,a(e.parameterJson.fan),1)])])):p("",!0),e.parameterJson&&e.parameterJson.door?(i(),m("div",{key:2,class:f(["inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full",[e.parameterJson.door=="close"?"bg-green-200":"bg-red-200"]])},[t("div",ft,[xt,t("span",null,a(e.parameterJson.door=="open"?"Open":"Close"),1)])],2)):p("",!0),e.parameterJson&&e.parameterJson.CoinCnt?(i(),m("div",{key:3,class:f(["inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full",[e.parameterJson.CoinCnt>1600?"bg-green-200":"bg-red-200"]])},[t("div",ht,[yt,t("span",null,a((e.parameterJson.CoinCnt/100).toFixed(2)),1)])],2)):p("",!0)])]),_:2},1032,["currentIndex","totalLength"]),o(g,{currentIndex:_,totalLength:d.vends.length,inputClass:"text-center"},{default:l(()=>[r(a(e.serial_num),1)]),_:2},1032,["currentIndex","totalLength"]),o(g,{currentIndex:_,totalLength:d.vends.length,inputClass:"text-center"},{default:l(()=>[e.latestVendBinding&&e.latestVendBinding.customer&&e.latestVendBinding.customer.deliveryAddress?(i(),m("span",bt,a(e.postcode),1)):p("",!0)]),_:2},1032,["currentIndex","totalLength"]),o(g,{currentIndex:_,totalLength:d.vends.length,inputClass:"text-center"},{default:l(()=>[r(a(e.firmware_ver),1)]),_:2},1032,["currentIndex","totalLength"])]))),128)),d.vends.data.length?p("",!0):(i(),m("tr",vt,Ct))])])]),d.vends.data.length?(i(),R(H,{key:0,links:d.vends.links,meta:d.vends.meta},null,8,["links","meta"])):p("",!0)])])])]}),_:1})],64))}};export{Ot as default};
