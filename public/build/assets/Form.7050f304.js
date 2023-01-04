import{i as c,u as _,j as S,o as i,c as k,a as t,w as d,T as C,d as s,g as f,p as $,t as b,f as m,b as g,e as j}from"./app.7e9af5a6.js";import{_ as V}from"./Button.24c8bfa0.js";import{_ as u}from"./FormInput.6342f540.js";import{_ as B}from"./Modal.a480b1af.js";import{_ as h}from"./MultiSelect.eca5a602.js";import{r as O}from"./ArrowUturnLeftIcon.0429b1f0.js";import{r as U}from"./CheckCircleIcon.017a5e8a.js";import"./open-closed.44e72380.js";const N={class:"flex flex-col md:flex-row space-x-2"},M={key:0,class:"text-gray-600"},E={key:1},T={key:2,class:"text-gray-600"},F=["onSubmit"],P={class:"grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6"},D={class:"col-span-12 sm:col-span-6"},q=m(" Name "),L={class:"col-span-12 sm:col-span-6"},R=m(" Email "),z={class:"col-span-12 sm:col-span-6"},A=m(" Username "),G={class:"col-span-12 sm:col-span-6"},H={class:"col-span-12 sm:col-span-6"},I=s("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Role ",-1),J={class:"col-span-12 sm:col-span-6"},K=s("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Operator ",-1),Q={class:"sm:col-span-6"},W={class:"flex space-x-1 mt-5 justify-end"},X=s("span",null," Back ",-1),Y=s("span",null," Save ",-1),de={__name:"Form",props:{user:Object,countries:Object,operators:Object,roles:Object,type:String,showModal:Boolean},emits:["modalClose"],setup(n,{emit:v}){const r=n,e=c(_(p())),x=c([]),y=c([]);S(()=>{e.value=r.user?_({...p(),...r.user}):_(p()),x.value=r.operators.data,y.value=r.roles});function p(){return{name:"",email:"",username:"",password:"",operator_id:""}}function w(){e.value.clearErrors(),r.type==="create"&&e.value.transform(l=>({...l,operator_id:l.operator_id.id})).post("/users/create",{onSuccess:()=>{v("modalClose")},preserveState:!0,replace:!0}),r.type==="update"&&e.value.transform(l=>({...l,operator_id:l.operator_id.id})).post("/users/"+e.value.id+"/update",{onSuccess:()=>{v("modalClose")},preserveState:!0,replace:!0})}return(l,o)=>(i(),k(C,{to:"body"},[t(B,{open:n.showModal,onModalClose:o[7]||(o[7]=a=>l.$emit("modalClose"))},{header:d(()=>[s("div",N,[r.user?(i(),f("span",M," Editing ")):$("",!0),r.user?(i(),f("span",E,b(r.user.name),1)):(i(),f("span",T," Create New User "))])]),default:d(()=>[s("form",{onSubmit:j(w,["prevent"]),id:"submit"},[s("div",P,[s("div",D,[t(u,{modelValue:e.value.name,"onUpdate:modelValue":o[0]||(o[0]=a=>e.value.name=a),error:e.value.errors.name,required:"true"},{default:d(()=>[q]),_:1},8,["modelValue","error"])]),s("div",L,[t(u,{modelValue:e.value.email,"onUpdate:modelValue":o[1]||(o[1]=a=>e.value.email=a),error:e.value.errors.email},{default:d(()=>[R]),_:1},8,["modelValue","error"])]),s("div",z,[t(u,{modelValue:e.value.username,"onUpdate:modelValue":o[2]||(o[2]=a=>e.value.username=a),error:e.value.errors.username},{default:d(()=>[A]),_:1},8,["modelValue","error"])]),s("div",G,[t(u,{modelValue:e.value.password,"onUpdate:modelValue":o[3]||(o[3]=a=>e.value.password=a),error:e.value.errors.password,placeholderStr:[n.type=="update"?"Leave blank for same password":""],inputType:"password",autocomplete:"new-password"},{default:d(()=>[m(" Password "+b(n.type=="update"?"(Override)":""),1)]),_:1},8,["modelValue","error","placeholderStr"])]),s("div",H,[I,t(h,{modelValue:e.value.role_id,"onUpdate:modelValue":o[4]||(o[4]=a=>e.value.role_id=a),options:y.value,trackBy:"id",valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),s("div",J,[K,t(h,{modelValue:e.value.operator_id,"onUpdate:modelValue":o[5]||(o[5]=a=>e.value.operator_id=a),options:x.value,trackBy:"id",valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])]),s("div",Q,[s("div",W,[t(V,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1",onClick:o[6]||(o[6]=a=>l.$emit("modalClose")),form:"submit"},{default:d(()=>[t(g(O),{class:"w-4 h-4"}),X]),_:1}),t(V,{type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:d(()=>[t(g(U),{class:"w-4 h-4"}),Y]),_:1})])])],40,F)]),_:1},8,["open"])]))}};export{de as default};
